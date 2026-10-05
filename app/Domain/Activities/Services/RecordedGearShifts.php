<?php

namespace App\Domain\Activities\Services;

use App\Models\Activity;

final class RecordedGearShifts
{
    /** @return array{total: int, front: int, rear: int, unknown: int, ignored: int, rows: list<array<string, mixed>>, truncated: bool, status: string} */
    public function report(Activity $activity): array
    {
        $events = $activity->events->where('type', 'shift')->sortBy(fn ($event) => $event->elapsed_seconds
            ?? ($event->recorded_at ? $event->recorded_at->getTimestamp() - $activity->started_at->getTimestamp() : PHP_INT_MAX));
        $front = 0;
        $rear = 0;
        $rows = [];
        foreach ($events as $event) {
            $direction = $event->data['direction'] ?? null;
            $front += (int) ($direction === 'front');
            $rear += (int) ($direction === 'rear');
            if (count($rows) >= 200) {
                continue;
            }
            $values = [];
            foreach (['front_gear_number', 'front_gear_teeth', 'rear_gear_number', 'rear_gear_teeth'] as $field) {
                $value = $event->data[$field] ?? null;
                $values[$field] = is_int($value) && $value >= 1 && $value <= 255 ? $value : null;
            }
            $rows[] = [
                ...$values,
                'elapsed_seconds' => $event->elapsed_seconds,
                'direction' => in_array($direction, ['front', 'rear'], true) ? $direction : null,
                'ratio' => $values['front_gear_teeth'] && $values['rear_gear_teeth'] ? round($values['front_gear_teeth'] / $values['rear_gear_teeth'], 2) : null,
            ];
        }
        $ignored = $activity->metadata['fit_parser']['shift_events_ignored'] ?? 0;
        $status = $activity->metadata['fit_parser']['shift_diagnostics']['status'] ?? null;
        if ($events->isNotEmpty()) {
            $status = 'available';
        } elseif (! is_string($status) || ! in_array($status, ['absent', 'described_only', 'unsupported', 'incomplete'], true)) {
            $status = 'not_evaluated';
        }

        return [
            'total' => $events->count(), 'front' => $front, 'rear' => $rear, 'unknown' => $events->count() - $front - $rear,
            'ignored' => is_int($ignored) && $ignored > 0 ? $ignored : 0,
            'rows' => $rows, 'truncated' => $events->count() > 200,
            'status' => $status,
        ];
    }
}
