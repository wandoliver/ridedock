<?php

namespace App\Domain\Activities\Services;

use App\Domain\Activities\Data\ProviderActivity;
use App\Models\Activity;
use App\Models\ActivitySource;
use App\Models\ProviderConnection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final class ActivityImporter
{
    public function import(ProviderConnection $connection, ProviderActivity $payload): ActivitySource
    {
        return DB::transaction(function () use ($connection, $payload): ActivitySource {
            $source = ActivitySource::query()
                ->with('activity')
                ->where('provider_connection_id', $connection->id)
                ->where('external_id', $payload->externalId)
                ->first();

            $deduplicationKey = $this->deduplicationKey($payload);
            $activity = $source === null
                ? Activity::query()
                    ->where('user_id', $connection->user_id)
                    ->where('deduplication_key', $deduplicationKey)
                    ->first()
                : $source->activity;

            $incoming = $this->activityAttributes($payload);
            $incomingPriority = (int) config("activity-providers.{$connection->provider->value}.priority", 0);

            if (! $activity) {
                $activity = new Activity([
                    ...$incoming,
                    'user_id' => $connection->user_id,
                    'deduplication_key' => $deduplicationKey,
                    'metadata' => [
                        'core_provider' => $connection->provider->value,
                        'core_provider_priority' => $incomingPriority,
                    ],
                ]);
                $activity->save();
            } else {
                $currentPriority = (int) Arr::get($activity->metadata, 'core_provider_priority', 0);
                $sameSource = $source !== null;

                foreach ($incoming as $key => $value) {
                    if ($value === null) {
                        continue;
                    }

                    if ($sameSource || $incomingPriority >= $currentPriority || $activity->{$key} === null) {
                        $activity->{$key} = $value;
                    }
                }

                if ($incomingPriority >= $currentPriority) {
                    $activity->metadata = [
                        ...($activity->metadata ?? []),
                        'core_provider' => $connection->provider->value,
                        'core_provider_priority' => $incomingPriority,
                    ];
                }

                $activity->save();
            }

            return ActivitySource::query()->updateOrCreate(
                [
                    'provider_connection_id' => $connection->id,
                    'external_id' => $payload->externalId,
                ],
                [
                    'activity_id' => $activity->id,
                    'raw_payload' => $payload->raw,
                    'external_updated_at' => $payload->externalUpdatedAt,
                    'imported_at' => now(),
                    'deleted_at_provider' => null,
                ],
            );
        });
    }

    public function deduplicationKey(ProviderActivity $payload): string
    {
        $startedMinute = $payload->startedAt->startOfMinute()->toIso8601String();
        $distanceBucket = $payload->distanceM === null ? 'x' : (string) (round($payload->distanceM / 100) * 100);
        $durationBucket = $payload->movingTimeS === null ? 'x' : (string) (round($payload->movingTimeS / 60) * 60);

        return hash('sha256', implode('|', [$startedMinute, $distanceBucket, $durationBucket]));
    }

    /** @return array<string, mixed> */
    private function activityAttributes(ProviderActivity $payload): array
    {
        return [
            'name' => $payload->name,
            'type' => $payload->type,
            'started_at' => $payload->startedAt,
            'timezone' => $payload->timezone,
            'distance_m' => $payload->distanceM,
            'moving_time_s' => $payload->movingTimeS,
            'elapsed_time_s' => $payload->elapsedTimeS,
            'elevation_gain_m' => $payload->elevationGainM,
            'average_speed_mps' => $payload->averageSpeedMps,
            'max_speed_mps' => $payload->maxSpeedMps,
            'average_heart_rate' => $payload->averageHeartRate,
            'max_heart_rate' => $payload->maxHeartRate,
            'average_cadence' => $payload->averageCadence,
            'max_cadence' => $payload->maxCadence,
            'average_power' => $payload->averagePower,
            'weighted_average_power' => $payload->weightedAveragePower,
            'max_power' => $payload->maxPower,
            'energy_kj' => $payload->energyKj,
            'summary_polyline' => $payload->summaryPolyline,
        ];
    }
}
