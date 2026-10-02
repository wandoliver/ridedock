<?php

namespace App\Domain\Training\Services;

use App\Models\Activity;
use App\Models\User;
use Carbon\CarbonImmutable;

final class SensorZoneDistribution
{
    public function __construct(private readonly TrainingZones $zones, private readonly RecordedSensorTimeline $timeline) {}

    /** @return array<string, mixed> */
    public function activity(Activity $activity): array
    {
        $activity->loadMissing('streams');
        $profile = $this->zones->forActivity($activity);
        $result = [];
        $duration = $this->timeline->duration($activity);
        foreach (['power', 'heart_rate'] as $sensor) {
            $ranges = $this->zones->ranges($profile, $sensor);
            $seconds = array_fill_keys($sensor === 'power' ? ['P1', 'P2', 'P3', 'P4', 'P5', 'P6'] : ['HR<50', 'H1', 'H2', 'H3', 'H4', 'H5', 'HR>100'], 0.0);
            foreach ($this->timeline->segments($activity, $sensor) as $segment) {
                foreach ($ranges as $range) {
                    if ($segment['value'] >= $range['lower'] && ($range['upper'] === null || $segment['value'] < $range['upper'] + 1)) {
                        $seconds[$range['label']] += $segment['end'] - $segment['start'];
                        break;
                    }
                }
            }
            $covered = array_sum($seconds);
            $result[$sensor] = [
                'available' => $covered > 0, 'seconds' => $seconds, 'covered_seconds' => $covered,
                'duration_seconds' => $duration, 'unknown_seconds' => max(0, $duration - $covered),
                'coverage_percent' => $duration > 0 ? round($covered / $duration * 100, 1) : null,
                'reason' => $ranges === [] ? 'reference_missing' : ($covered > 0 ? null : 'samples_missing'),
            ];
        }
        return $result;
    }

    /** @return array<string, mixed> */
    public function history(User $user, CarbonImmutable $asOf): array
    {
        $totals = [];
        $start = $asOf->startOfWeek(CarbonImmutable::MONDAY)->subWeeks(5);
        $user->activities()->whereBetween('started_at', [$start->subDay(), $asOf])
            ->with(['streams' => fn ($query) => $query->whereIn('type', ['power', 'heart_rate'])])
            ->chunkById(20, function ($rides) use (&$totals, $start, $asOf): void {
                foreach ($rides as $ride) {
                    if ($ride->localStartedAt()->toDateString() < $start->toDateString() || $ride->localStartedAt()->toDateString() > $asOf->toDateString()) {
                        continue;
                    }
                    foreach ($this->activity($ride) as $sensor => $distribution) {
                        $totals[$sensor] ??= ['seconds' => array_fill_keys(array_keys($distribution['seconds']), 0.0), 'covered_seconds' => 0.0, 'duration_seconds' => 0.0, 'unknown_seconds' => 0.0];
                        foreach ($distribution['seconds'] as $zone => $seconds) {
                            $totals[$sensor]['seconds'][$zone] += $seconds;
                        }
                        foreach (['covered_seconds', 'duration_seconds', 'unknown_seconds'] as $key) {
                            $totals[$sensor][$key] += $distribution[$key];
                        }
                    }
                }
            });
        foreach ($totals as &$total) {
            $total['available'] = $total['covered_seconds'] > 0;
            $total['coverage_percent'] = $total['duration_seconds'] > 0 ? round($total['covered_seconds'] / $total['duration_seconds'] * 100, 1) : null;
            $total['reason'] = $total['available'] ? null : 'samples_missing';
        }
        return $totals;
    }
}
