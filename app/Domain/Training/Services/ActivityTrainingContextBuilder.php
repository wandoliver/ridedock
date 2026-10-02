<?php

namespace App\Domain\Training\Services;

use App\Models\Activity;
use App\Models\AthleteProfile;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class ActivityTrainingContextBuilder
{
    public const SCHEMA_VERSION = 4;

    /** @return array<string, mixed> */
    public function build(Activity $activity): array
    {
        $activity->loadMissing('user.athleteProfile');
        $profile = $activity->user->athleteProfile;
        $windowEndsAt = $activity->started_at;
        $windowStartsAt = $windowEndsAt->subWeeks(6);

        /** @var Collection<int, Activity> $history */
        $history = Activity::query()
            ->whereBelongsTo($activity->user)
            ->whereKeyNot($activity->getKey())
            ->whereBetween('started_at', [$windowStartsAt, $windowEndsAt])
            ->oldest('started_at')
            ->get([
                'id', 'user_id', 'name', 'type', 'started_at', 'distance_m',
                'moving_time_s', 'elevation_gain_m', 'average_heart_rate',
                'max_heart_rate', 'average_cadence', 'average_power',
                'weighted_average_power', 'max_power', 'energy_kj', 'rpe', 'timezone',
            ]);

        return [
            'schema_version' => self::SCHEMA_VERSION,
            'purpose' => 'activity_analysis',
            'generated_at' => CarbonImmutable::now()->toIso8601String(),
            'athlete_profile' => $this->profile($profile),
            'activity' => $this->activityMetrics($activity, $profile),
            'recent_training' => [
                'window_start' => $windowStartsAt->toDateString(),
                'window_end' => $windowEndsAt->toDateString(),
                'summary' => $this->summary($history),
                'weeks' => $this->weeks($history, $windowEndsAt),
            ],
            'data_gaps' => $this->dataGaps($profile, $history),
            'privacy' => [
                'gps_coordinates_included' => false,
                'provider_payloads_included' => false,
                'activity_notes_included' => false,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function profile(?AthleteProfile $profile): array
    {
        return [
            'goal' => $profile?->goal,
            'goal_date' => $profile?->goal_date?->toDateString(),
            'experience_level' => $profile?->experience_level,
            'weekly_hours' => $profile?->weekly_hours,
            'available_days' => $profile->available_days ?? [],
            'ftp_watts' => $profile?->ftp_watts,
            'max_heart_rate' => $profile?->max_heart_rate,
            'constraints' => $profile?->constraints,
        ];
    }

    /** @return array<string, mixed> */
    private function activityMetrics(Activity $activity, ?AthleteProfile $profile): array
    {
        $durationMinutes = $this->minutes($activity->moving_time_s);

        return [
            'id' => $activity->id,
            'name' => $activity->name,
            'type' => $activity->type,
            'started_at' => $activity->started_at->toIso8601String(),
            'duration_minutes' => $durationMinutes,
            'distance_km' => $this->rounded($activity->distance_m, 1000),
            'average_speed_kmh' => $activity->average_speed_mps === null
                ? null
                : round($activity->average_speed_mps * 3.6, 1),
            'elevation_gain_m' => $this->rounded($activity->elevation_gain_m),
            'average_heart_rate_bpm' => $activity->average_heart_rate,
            'max_heart_rate_bpm' => $activity->max_heart_rate,
            'average_power_watts' => $this->rounded($activity->average_power),
            'weighted_average_power_watts' => $this->rounded($activity->weighted_average_power),
            'max_power_watts' => $this->rounded($activity->max_power),
            'average_cadence_rpm' => $this->rounded($activity->average_cadence),
            'energy_kj' => $this->rounded($activity->energy_kj),
            'rpe' => $activity->rpe,
            'session_rpe_load' => $durationMinutes !== null && $activity->rpe !== null
                ? round($durationMinutes * $activity->rpe, 1)
                : null,
            'weighted_power_to_ftp_ratio' => $activity->weighted_average_power !== null
                && $profile?->ftp_watts !== null
                && $profile->ftp_watts > 0
                    ? round($activity->weighted_average_power / $profile->ftp_watts, 3)
                    : null,
            'average_heart_rate_to_max_ratio' => $activity->average_heart_rate !== null
                && $profile?->max_heart_rate !== null
                && $profile->max_heart_rate > 0
                    ? round($activity->average_heart_rate / $profile->max_heart_rate, 3)
                    : null,
        ];
    }

    /** @param Collection<int, Activity> $activities
     * @return array<string, mixed>
     */
    private function summary(Collection $activities): array
    {
        return app(TrainingStatistics::class)->summary($activities);
    }

    /** @param Collection<int, Activity> $activities
     * @return list<array<string, mixed>>
     */
    private function weeks(Collection $activities, CarbonImmutable $windowEndsAt): array
    {
        return app(TrainingStatistics::class)->weeks($activities, $windowEndsAt);
    }

    /** @param Collection<int, Activity> $history
     * @return list<string>
     */
    private function dataGaps(?AthleteProfile $profile, Collection $history): array
    {
        $gaps = [];

        if ($profile === null) {
            $gaps[] = 'athlete_profile_missing';
        }
        if ($profile?->goal === null) {
            $gaps[] = 'training_goal_missing';
        }
        if ($history->count() < 3) {
            $gaps[] = 'limited_training_history';
        }

        return $gaps;
    }

    private function minutes(?int $seconds): ?float
    {
        return $seconds === null ? null : round($seconds / 60, 1);
    }

    private function rounded(?float $value, float $divisor = 1): ?float
    {
        return $value === null ? null : round($value / $divisor, 1);
    }
}
