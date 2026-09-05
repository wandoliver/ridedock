<?php

namespace App\Domain\Training\Services;

use App\Models\Activity;
use App\Models\AiAnalysis;
use Illuminate\Support\Collection;

final class PlanActivityMatcher
{
    /** @return Collection<int, array<string, mixed>> */
    public function days(?AiAnalysis $plan): Collection
    {
        $days = $plan?->output['days'] ?? [];
        if (! is_array($days)) {
            return collect([]);
        }

        return collect(array_values(array_filter($days, fn ($day) => is_array($day) && is_string($day['date'] ?? null))))
            ->map($this->normalizeDay(...));
    }

    /**
     * @param  array<mixed>  $day
     * @return array<string, mixed>
     */
    private function normalizeDay(array $day): array
    {
        $fields = [];
        foreach ($day as $key => $value) {
            if (is_string($key)) {
                $fields[$key] = $value;
            }
        }

        return $fields;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $days
     * @param  Collection<int, Activity>  $activities
     * @return Collection<string, Collection<int, Activity>>
     */
    public function match(Collection $days, Collection $activities): Collection
    {
        $manualIds = [];
        foreach ($days as $day) {
            foreach (is_array($day['linked_activity_ids'] ?? null) ? $day['linked_activity_ids'] : [] as $id) {
                if (is_string($id)) {
                    $manualIds[] = $id;
                }
            }
        }

        return $days->mapWithKeys(function (array $day) use ($activities, $manualIds): array {
            $rides = $activities->filter(function (Activity $activity) use ($day, $manualIds): bool {
                if (isset($day['linked_activity_ids'])) {
                    return in_array($activity->id, $day['linked_activity_ids'], true);
                }

                return ! in_array($activity->id, $manualIds, true)
                    && $activity->localStartedAt()->toDateString() === $day['date'];
            });

            return [$day['date'] => $rides];
        });
    }
}
