<?php

namespace App\Domain\Activities\Services;

use App\Domain\Activities\Contracts\ActivitySourceEnricher;
use App\Domain\Activities\Contracts\ActivitySourceFileParser;
use App\Models\ActivitySource;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class FitActivitySourceEnricher implements ActivitySourceEnricher
{
    private const STREAM_TYPES = [
        'latlng',
        'heart_rate',
        'cadence',
        'power',
        'speed',
        'altitude',
    ];

    private const SUMMARY_FIELDS = [
        'started_at',
        'distance_m',
        'moving_time_s',
        'elapsed_time_s',
        'elevation_gain_m',
        'average_speed_mps',
        'max_speed_mps',
        'average_heart_rate',
        'max_heart_rate',
        'average_cadence',
        'max_cadence',
        'average_power',
        'weighted_average_power',
        'max_power',
        'energy_kj',
    ];

    public function __construct(private readonly ActivitySourceFileParser $parser) {}

    public function enrich(ActivitySource $source): void
    {
        if (! $source->source_file_path || ! $source->source_file_checksum) {
            throw new RuntimeException('The activity source has no archived FIT file.');
        }

        $disk = Storage::disk(config('activity-providers.sync.source_file_disk'));
        $path = $disk->path($source->source_file_path);
        $checksum = hash_file('sha256', $path);

        if (! is_string($checksum) || ! hash_equals($source->source_file_checksum, $checksum)) {
            throw new RuntimeException('The archived FIT source checksum does not match.');
        }

        $result = $this->parser->parse($path);

        DB::transaction(function () use ($source, $result): void {
            $activity = $source->activity()->lockForUpdate()->firstOrFail();
            $summary = Arr::only($result['summary'], self::SUMMARY_FIELDS);

            $activity->forceFill(array_filter(
                $summary,
                static fn (mixed $value): bool => $value !== null,
            ));
            $activity->metadata = array_merge($activity->metadata ?? [], [
                'fit_parser' => $result['parser'],
            ]);
            $activity->save();

            $presentStreamTypes = [];

            foreach ($result['streams'] as $type => $stream) {
                if (! in_array($type, self::STREAM_TYPES, true)) {
                    continue;
                }

                $presentStreamTypes[] = $type;
                $activity->streams()->updateOrCreate(
                    ['type' => $type],
                    [
                        'series_type' => 'time',
                        'resolution' => 'high',
                        'data' => $stream['data'],
                        'metadata' => $stream['metadata'] ?: null,
                    ],
                );
            }

            $activity->streams()
                ->whereIn('type', self::STREAM_TYPES)
                ->when($presentStreamTypes !== [], fn ($query) => $query->whereNotIn('type', $presentStreamTypes))
                ->delete();

            $activity->events()->where('type', 'shift')->delete();

            foreach ($result['events'] as $event) {
                if ($event['type'] !== 'shift') {
                    continue;
                }

                $activity->events()->create([
                    'type' => 'shift',
                    'elapsed_seconds' => $event['elapsed_seconds'],
                    'recorded_at' => $event['recorded_at'],
                    'data' => $event['data'],
                ]);
            }

            $source->forceFill([
                'metadata' => array_merge($source->metadata ?? [], [
                    'fit_parser' => $result['parser'],
                    'enriched_at' => now()->toIso8601String(),
                ]),
            ])->save();
        });
    }
}
