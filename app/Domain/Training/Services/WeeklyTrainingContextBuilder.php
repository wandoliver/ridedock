<?php

namespace App\Domain\Training\Services;

use App\Domain\Training\Enums\AiAnalysisStatus;
use App\Models\Activity;
use App\Models\AthleteProfile;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class WeeklyTrainingContextBuilder
{
    public const SCHEMA_VERSION = 5;

    /** @return array<string, mixed> */
    public function build(User $user, ?CarbonImmutable $planStartsAt = null): array
    {
        $user->loadMissing('athleteProfile');
        $profile = $user->athleteProfile;
        $planStartsAt ??= CarbonImmutable::today(config('app.timezone'))->addDay();
        $historyEndsAt = $planStartsAt->subMicrosecond()->min(CarbonImmutable::now());
        $historyStartsAt = $historyEndsAt->subWeeks(6);

        /** @var Collection<int, Activity> $activities */
        $activities = $user->activities()
            ->whereBetween('started_at', [$historyStartsAt, $historyEndsAt])
            ->oldest('started_at')
            ->get([
                'id', 'user_id', 'type', 'started_at', 'distance_m', 'moving_time_s',
                'elevation_gain_m', 'average_heart_rate', 'average_cadence',
                'average_power', 'weighted_average_power', 'rpe', 'timezone',
            ]);

        return [
            'schema_version' => self::SCHEMA_VERSION,
            'purpose' => 'weekly_training_plan',
            'generated_at' => CarbonImmutable::now()->toIso8601String(),
            'plan_start' => $planStartsAt->toDateString(),
            'plan_end' => $planStartsAt->addDays(6)->toDateString(),
            'zone_profile' => app(TrainingZones::class)->context(app(TrainingZones::class)->at($user, CarbonImmutable::now())),
            'athlete_profile' => $this->profile($profile),
            'strategy' => $this->strategy($user),
            'plan_days' => $this->planDays($planStartsAt, $profile->available_days ?? []),
            'recent_training' => [
                'window_start' => $historyStartsAt->toDateString(),
                'window_end' => $historyEndsAt->toDateString(),
                'sensor_zone_distribution' => app(SensorZoneDistribution::class)->history($user, $historyEndsAt),
                'weeks' => app(TrainingStatistics::class)->weeks($activities, $historyEndsAt),
                'six_week_summary' => $this->summary($activities),
                'last_seven_days' => $this->summary($activities->filter(
                    fn (Activity $activity): bool => $activity->started_at->greaterThanOrEqualTo($historyEndsAt->subDays(7)),
                )),
                'sessions' => $activities->take(-12)->map(fn (Activity $activity): array => $this->session($activity))->values()->all(),
            ],
            'data_gaps' => $this->dataGaps($profile, $activities),
            'privacy' => [
                'gps_coordinates_included' => false,
                'provider_payloads_included' => false,
                'activity_notes_included' => false,
            ],
        ];
    }

    /** @return array{goal_plan: array<string, mixed>|null, training_block: array<string, mixed>|null} */
    private function strategy(User $user): array
    {
        $goalPlan = $user->aiAnalyses()
            ->where('type', 'goal_plan')
            ->where('status', AiAnalysisStatus::Completed)
            ->latest()
            ->first();
        $trainingBlock = $user->aiAnalyses()
            ->where('type', 'training_block')
            ->where('status', AiAnalysisStatus::Completed)
            ->latest()
            ->first();

        if (($trainingBlock?->context['goal_plan_analysis_id'] ?? null) !== $goalPlan?->id) {
            $trainingBlock = null;
        }

        return [
            'goal_plan' => $goalPlan?->output,
            'training_block' => $trainingBlock?->output,
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
            'constraints' => $profile?->constraints,
            'optional_signals' => [
                'ftp_watts' => $profile?->ftp_watts,
                'max_heart_rate' => $profile?->max_heart_rate,
            ],
        ];
    }

    /** @param list<string> $availableDays
     * @return list<array{date: string, weekday: string, available_for_training: bool}>
     */
    private function planDays(CarbonImmutable $planStartsAt, array $availableDays): array
    {
        $days = [];

        for ($offset = 0; $offset < 7; $offset++) {
            $date = $planStartsAt->addDays($offset);
            $weekday = strtolower($date->englishDayOfWeek);
            $days[] = [
                'date' => $date->toDateString(),
                'weekday' => $weekday,
                'available_for_training' => in_array($weekday, $availableDays, true),
            ];
        }

        return $days;
    }

    /** @param Collection<int, Activity> $activities
     * @return array<string, mixed>
     */
    private function summary(Collection $activities): array
    {
        return app(TrainingStatistics::class)->summary($activities);
    }

    /** @return array<string, mixed> */
    private function session(Activity $activity): array
    {
        return [
            'date' => $activity->localStartedAt()->toDateString(),
            'type' => $activity->type,
            'duration_minutes' => $activity->moving_time_s === null ? null : round($activity->moving_time_s / 60, 1),
            'distance_km' => $activity->distance_m === null ? null : round($activity->distance_m / 1000, 1),
            'elevation_gain_m' => $activity->elevation_gain_m === null ? null : round($activity->elevation_gain_m, 0),
            'rpe' => $activity->rpe,
            'session_rpe_load' => $activity->moving_time_s !== null && $activity->rpe !== null
                ? round(($activity->moving_time_s / 60) * $activity->rpe, 1)
                : null,
            'optional_signals' => [
                'average_heart_rate_bpm' => $activity->average_heart_rate,
                'average_cadence_rpm' => $activity->average_cadence,
                'average_power_watts' => $activity->average_power,
                'weighted_average_power_watts' => $activity->weighted_average_power,
            ],
        ];
    }

    /** @param Collection<int, Activity> $activities
     * @return list<string>
     */
    private function dataGaps(?AthleteProfile $profile, Collection $activities): array
    {
        $gaps = [];

        if ($profile?->goal === null) {
            $gaps[] = 'training_goal_missing';
        }
        if ($activities->count() < 3) {
            $gaps[] = 'limited_training_history';
        }

        return $gaps;
    }
}
