<?php

namespace App\Domain\Training\Services;

use App\Models\Activity;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class TrainingStatistics
{
    /** @param Collection<int, Activity> $activities
     * @return array<string, mixed>
     */
    public function summary(Collection $activities): array
    {
        $seconds = 0;
        $ratedSeconds = 0;
        $load = 0.0;
        $ratedCount = 0;
        $missingDuration = 0;
        $ratings = [];
        $effort = ['easy' => 0.0, 'moderate' => 0.0, 'hard' => 0.0, 'unrated' => 0.0];

        foreach ($activities as $activity) {
            $duration = $activity->moving_time_s;
            $rated = $activity->rpe !== null && $activity->rpe >= 1 && $activity->rpe <= 10;
            if ($rated) {
                $ratings[] = $activity->rpe;
            }
            if ($duration === null || $duration < 0) {
                $missingDuration++;
                continue;
            }
            $seconds += $duration;
            $bucket = ! $rated ? 'unrated' : ($activity->rpe <= 3 ? 'easy' : ($activity->rpe <= 6 ? 'moderate' : 'hard'));
            $effort[$bucket] += $duration / 60;
            if ($rated) {
                $ratedCount++;
                $ratedSeconds += $duration;
                $load += $duration / 60 * $activity->rpe;
            }
        }

        return [
            'activity_count' => $activities->count(),
            'duration_hours' => round($seconds / 3600, 1),
            'duration_minutes' => round($seconds / 60, 1),
            'distance_km' => round($activities->sum(fn (Activity $ride): float => max(0.0, $ride->distance_m ?? 0.0)) / 1000, 1),
            'elevation_gain_m' => round($activities->sum(fn (Activity $ride): float => max(0.0, $ride->elevation_gain_m ?? 0.0)), 0),
            'average_rpe' => $ratings === [] ? null : round(array_sum($ratings) / count($ratings), 1),
            'session_rpe_load' => $ratedCount > 0 || $activities->isEmpty() ? round($load, 1) : null,
            'load_complete' => $ratedCount === $activities->count(),
            'rated_activity_count' => $ratedCount,
            'missing_duration_count' => $missingDuration,
            'rpe_coverage_percent' => $seconds > 0 ? round($ratedSeconds / $seconds * 100, 1) : null,
            'effort_minutes' => array_map(fn (float $minutes): float => round($minutes, 1), $effort),
        ];
    }

    /** @param Collection<int, Activity> $activities
     * @return list<array<string, mixed>>
     */
    public function weeks(Collection $activities, CarbonImmutable $asOf): array
    {
        $currentWeek = $asOf->startOfWeek(CarbonImmutable::MONDAY);
        $weeks = [];
        for ($offset = 5; $offset >= 0; $offset--) {
            $start = $currentWeek->subWeeks($offset);
            $end = $start->addDays(6);
            $rides = $activities->filter(fn (Activity $ride): bool => $ride->started_at->lessThanOrEqualTo($asOf)
                && $ride->localStartedAt()->toDateString() >= $start->toDateString()
                && $ride->localStartedAt()->toDateString() <= $end->toDateString());
            $weeks[] = [
                'week_start' => $start->toDateString(),
                'week_end' => $end->toDateString(),
                'partial' => $offset === 0,
                ...$this->summary($rides),
            ];
        }
        return $weeks;
    }

    /** @return array<string, mixed> */
    public function report(User $user, CarbonImmutable $asOf): array
    {
        $start = $asOf->startOfWeek(CarbonImmutable::MONDAY)->subWeeks(5);
        // A one-day buffer accommodates recordings whose local calendar date differs from UTC.
        $activities = $user->activities()->whereBetween('started_at', [$start->subDay(), $asOf])
            ->get(['id', 'started_at', 'timezone', 'moving_time_s', 'distance_m', 'elevation_gain_m', 'rpe']);
        $activities = $activities->filter(fn (Activity $ride): bool => $ride->localStartedAt()->toDateString() >= $start->toDateString()
            && $ride->localStartedAt()->toDateString() <= $asOf->toDateString())->values();
        $weeks = $this->weeks($activities, $asOf);
        $lastWeek = $weeks[4];
        $baseline = array_slice($weeks, 0, 4);
        $firstRecorded = $user->activities()->oldest('started_at')->first(['started_at', 'timezone']);
        $earliestDate = $firstRecorded?->localStartedAt()->toDateString();
        $hasHistory = $earliestDate !== null && $earliestDate <= $baseline[0]['week_start'];
        $volumeComplete = collect([...$baseline, $lastWeek])->every(fn (array $week): bool => $week['missing_duration_count'] === 0);
        $loadComplete = collect([...$baseline, $lastWeek])->every(fn (array $week): bool => $week['load_complete']);
        $baselineMinutes = array_sum(array_column($baseline, 'duration_minutes')) / 4;
        $baselineLoad = $loadComplete ? array_sum(array_column($baseline, 'session_rpe_load')) / 4 : null;

        return [
            'window_start' => $start->toDateString(),
            'window_end' => $asOf->toDateString(),
            'summary' => $this->summary($activities),
            'weeks' => $weeks,
            'comparison' => [
                'week_start' => $lastWeek['week_start'],
                'baseline_start' => $baseline[0]['week_start'],
                'baseline_end' => $baseline[3]['week_end'],
                'has_history' => $hasHistory,
                'volume_change_percent' => $hasHistory && $volumeComplete && $baselineMinutes > 0
                    ? round(($lastWeek['duration_minutes'] / $baselineMinutes - 1) * 100, 1) : null,
                'load_change_percent' => $hasHistory && $baselineLoad !== null && $baselineLoad > 0
                    ? round(($lastWeek['session_rpe_load'] / $baselineLoad - 1) * 100, 1) : null,
            ],
        ];
    }
}
