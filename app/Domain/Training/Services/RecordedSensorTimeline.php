<?php

namespace App\Domain\Training\Services;

use App\Models\Activity;
use App\Models\ActivityStream;

final class RecordedSensorTimeline
{
    /** @return list<array{start: float, end: float, value: float}> */
    public function segments(Activity $activity, string $sensor): array
    {
        $stream = $activity->streams->firstWhere('type', $sensor);
        if (! $stream instanceof ActivityStream) {
            return [];
        }
        $interval = $stream->metadata['interval_seconds'] ?? null;
        $points = [];
        foreach ($stream->data as $index => $sample) {
            $time = is_array($sample) ? ($sample['elapsed_seconds'] ?? null)
                : (is_numeric($interval) && (float) $interval > 0 ? $index * (float) $interval : null);
            $value = is_array($sample) ? ($sample['value'] ?? null) : $sample;
            if (! is_numeric($time) || ! is_finite((float) $time) || (float) $time < 0) {
                continue;
            }
            // Invalid values remain in the timeline so they break continuity.
            $points[(string) (float) $time] = [
                'time' => (float) $time,
                'value' => is_numeric($value) && is_finite((float) $value) && (float) $value >= 0
                    && ($sensor !== 'heart_rate' || (float) $value > 0) ? (float) $value : null,
            ];
        }
        $points = array_values($points);
        usort($points, fn (array $a, array $b): int => $a['time'] <=> $b['time']);
        $segments = [];
        foreach ($points as $index => $point) {
            $end = $points[$index + 1]['time'] ?? null;
            // Scalar series with explicit cadence include their last measured interval.
            if ($end === null && is_numeric($interval) && (float) $interval > 0 && ! is_array($stream->data[0] ?? null)) {
                $end = $point['time'] + (float) $interval;
            }
            if ($end === null || $point['value'] === null || $end - $point['time'] > 30) {
                continue;
            }
            if ($activity->elapsed_time_s !== null && $activity->elapsed_time_s >= 0) {
                $end = min($end, $activity->elapsed_time_s);
            }
            if ($end > $point['time']) {
                $segments[] = ['start' => $point['time'], 'end' => $end, 'value' => $point['value']];
            }
        }
        return $segments;
    }

    public function duration(Activity $activity): float
    {
        if ($activity->elapsed_time_s !== null && $activity->elapsed_time_s >= 0) {
            return (float) $activity->elapsed_time_s;
        }
        $last = 0.0;
        foreach ($activity->streams as $stream) {
            if (! in_array($stream->type, ['power', 'heart_rate'], true)) {
                continue;
            }
            foreach ($stream->data as $index => $sample) {
                $time = is_array($sample) ? ($sample['elapsed_seconds'] ?? null)
                    : (is_numeric($stream->metadata['interval_seconds'] ?? null) ? ($index + 1) * (float) $stream->metadata['interval_seconds'] : null);
                if (is_numeric($time) && is_finite((float) $time)) {
                    $last = max($last, (float) $time);
                }
            }
        }
        return $last > 0 ? $last : max(0, (float) ($activity->moving_time_s ?? 0));
    }
}
