<?php

namespace App\Domain\Training\Services;

use App\Models\Activity;
use App\Models\User;
use Carbon\CarbonImmutable;

final class ComparableEfficiency
{
    public function __construct(private readonly RecordedSensorTimeline $timeline, private readonly TrainingZones $zones) {}

    /** @return array<string, mixed> */
    public function assess(Activity $activity): array
    {
        $activity->loadMissing('streams');
        $ftp = $this->zones->forActivity($activity)?->ftp_watts;
        $result = ['available' => false, 'reason' => 'reference_missing', 'watts_per_bpm' => null,
            'mean_power_watts' => null, 'mean_heart_rate_bpm' => null, 'duration_seconds' => null,
            'window_start_seconds' => null, 'power_cv_percent' => null,
            'change_percent' => null, 'previous_date' => null,
            'method' => 'time-weighted mean watts / mean bpm; longest continuous >=20min steady 55–75% FTP window after first 10min; power CV <=10%'];
        if ($ftp === null || $ftp <= 0) {
            return $result;
        }
        $power = $this->timeline->segments($activity, 'power');
        $heart = $this->timeline->segments($activity, 'heart_rate');
        if ($power === [] || $heart === []) {
            return array_replace($result, ['reason' => 'samples_missing']);
        }
        $runs = [];
        $run = [];
        $i = 0;
        $j = 0;
        $end = null;
        while (isset($power[$i], $heart[$j])) {
            $p = $power[$i];
            $h = $heart[$j];
            $start = max(600.0, $p['start'], $h['start']);
            $stop = min($p['end'], $h['end']);
            if ($stop > $start && $p['value'] >= $ftp * .55 && $p['value'] <= $ftp * .75) {
                if ($end !== null && abs($start - $end) > .000001) {
                    $runs[] = $run;
                    $run = [];
                }
                $run[] = ['start' => $start, 'end' => $stop, 'power' => $p['value'], 'heart' => $h['value']];
                $end = $stop;
            }
            if ($p['end'] <= $h['end']) {
                $i++;
            }
            if ($h['end'] <= $p['end']) {
                $j++;
            }
        }
        if ($run !== []) {
            $runs[] = $run;
        }
        $best = null;
        foreach ($runs as $candidate) {
            $duration = $watts = $bpm = $squares = 0.0;
            foreach ($candidate as $segment) {
                $seconds = $segment['end'] - $segment['start'];
                $duration += $seconds;
                $watts += $segment['power'] * $seconds;
                $bpm += $segment['heart'] * $seconds;
                $squares += $segment['power'] ** 2 * $seconds;
            }
            if ($duration < 1200) {
                continue;
            }
            $meanPower = $watts / $duration;
            $meanHeart = $bpm / $duration;
            $cv = sqrt(max(0, $squares / $duration - $meanPower ** 2)) / $meanPower * 100;
            if ($cv > 10 || ($best !== null && $duration <= $best['duration_seconds'])) {
                continue;
            }
            $best = ['available' => true, 'reason' => null, 'watts_per_bpm' => round($meanPower / $meanHeart, 4),
                'mean_power_watts' => round($meanPower, 1), 'mean_heart_rate_bpm' => round($meanHeart, 1),
                'duration_seconds' => $duration, 'window_start_seconds' => $candidate[0]['start'], 'power_cv_percent' => round($cv, 1)];
        }
        return array_replace($result, $best ?? ['reason' => 'steady_window_missing']);
    }

    /** @return array<string, mixed> */
    public function report(Activity $activity): array
    {
        $current = $this->assess($activity);
        if (! $current['available'] || $activity->comparison_group === null) {
            return $current;
        }
        $activity->loadMissing('user');
        $activity->user->activities()->where('comparison_group', $activity->comparison_group)->where('type', $activity->type)
            ->where('started_at', '<', $activity->started_at)->where('started_at', '>=', $activity->started_at->subWeeks(6))
            ->with(['streams' => fn ($query) => $query->whereIn('type', ['power', 'heart_rate'])])
            ->orderByDesc('started_at')->orderByDesc('id')->chunk(20, function ($rides) use (&$current): ?bool {
                foreach ($rides as $ride) {
                    $previous = $this->assess($ride);
                    if ($this->similar($current, $previous)) {
                        $current['change_percent'] = round(($current['watts_per_bpm'] / $previous['watts_per_bpm'] - 1) * 100, 1);
                        $current['previous_date'] = $ride->localStartedAt()->toDateString();
                        return false;
                    }
                }
                return null;
            });
        return $current;
    }

    /** @return list<array<string, mixed>> */
    public function history(User $user, CarbonImmutable $asOf): array
    {
        $result = [];
        $start = $asOf->startOfWeek(CarbonImmutable::MONDAY)->subWeeks(5);
        $user->activities()->whereBetween('started_at', [$start->subDay(), $asOf])->whereNotNull('comparison_group')
            ->with(['streams' => fn ($query) => $query->whereIn('type', ['power', 'heart_rate'])])
            ->orderByDesc('started_at')->orderByDesc('id')->chunk(20, function ($rides) use (&$result, $start): void {
                foreach ($rides as $ride) {
                    if ($ride->localStartedAt()->toDateString() < $start->toDateString()) {
                        continue;
                    }
                    $report = $this->report($ride);
                    if ($report['available']) {
                        $result[] = [...$report, 'activity_id' => $ride->id, 'date' => $ride->localStartedAt()->toDateString(), 'group' => $ride->comparison_group];
                    }
                }
            });
        return $result;
    }

    /** @param array<string, mixed> $current
     * @param array<string, mixed> $previous
     */
    private function similar(array $current, array $previous): bool
    {
        return $previous['available'] && $previous['watts_per_bpm'] > 0
            && abs($current['duration_seconds'] / $previous['duration_seconds'] - 1) <= .20
            && abs($current['mean_power_watts'] / $previous['mean_power_watts'] - 1) <= .10;
    }
}
