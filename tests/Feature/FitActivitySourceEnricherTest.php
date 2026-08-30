<?php

namespace Tests\Feature;

use App\Domain\Activities\Contracts\ActivitySourceFileParser;
use App\Domain\Activities\Services\FitActivitySourceEnricher;
use App\Models\Activity;
use App\Models\ActivityEvent;
use App\Models\ActivitySource;
use App\Models\ActivityStream;
use App\Models\ProviderConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class FitActivitySourceEnricherTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_enriches_an_activity_idempotently_from_an_archived_fit_file(): void
    {
        Storage::fake('activity_sources');
        config()->set('activity-providers.sync.source_file_disk', 'activity_sources');

        $source = $this->sourceWithArchivedFile('valid fit');
        ActivityStream::factory()->for($source->activity)->create(['type' => 'cadence']);
        ActivityEvent::factory()->for($source->activity)->create(['type' => 'shift']);
        $enricher = new FitActivitySourceEnricher($this->parserReturning([
            'parser' => ['name' => 'fit-file-parser', 'version' => '5.0.2'],
            'summary' => [
                'distance_m' => 42500.5,
                'moving_time_s' => 5400,
                'average_power' => 225,
            ],
            'streams' => [
                'latlng' => [
                    'data' => [[
                        'elapsed_seconds' => 0,
                        'recorded_at' => '2026-08-30T07:00:00.000Z',
                        'value' => [46.4983, 11.3548],
                    ]],
                    'metadata' => ['unit' => 'degrees'],
                ],
                'power' => [
                    'data' => [['elapsed_seconds' => 0, 'value' => 210]],
                    'metadata' => ['unit' => 'watts'],
                ],
            ],
            'events' => [[
                'type' => 'shift',
                'elapsed_seconds' => 12,
                'recorded_at' => '2026-08-30T07:00:12.000Z',
                'data' => ['direction' => 'rear', 'rear_gear_number' => 7],
            ]],
        ]));

        $enricher->enrich($source);
        $enricher->enrich($source->fresh());

        $activity = $source->activity->fresh();
        $this->assertSame(42500.5, $activity->distance_m);
        $this->assertSame(5400, $activity->moving_time_s);
        $this->assertSame(225.0, $activity->average_power);
        $this->assertSame('5.0.2', $activity->metadata['fit_parser']['version']);
        $this->assertDatabaseCount('activity_streams', 2);
        $this->assertDatabaseMissing('activity_streams', ['type' => 'cadence']);
        $this->assertDatabaseHas('activity_streams', ['activity_id' => $activity->id, 'type' => 'latlng']);
        $this->assertSame(1, ActivityEvent::whereBelongsTo($activity)->where('type', 'shift')->count());
        $this->assertSame('5.0.2', $source->fresh()->metadata['fit_parser']['version']);
    }

    public function test_it_rejects_an_archived_file_with_a_changed_checksum(): void
    {
        Storage::fake('activity_sources');
        config()->set('activity-providers.sync.source_file_disk', 'activity_sources');

        $source = $this->sourceWithArchivedFile('original fit');
        Storage::disk('activity_sources')->put($source->source_file_path, 'changed fit');
        $enricher = new FitActivitySourceEnricher($this->parserReturning([]));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('checksum does not match');

        $enricher->enrich($source);
    }

    private function sourceWithArchivedFile(string $contents): ActivitySource
    {
        $user = User::factory()->create();
        $activity = Activity::factory()->for($user)->create();
        $connection = ProviderConnection::factory()->for($user)->create();
        $path = "{$user->id}/hammerhead/test.fit";
        Storage::disk('activity_sources')->put($path, $contents);

        return ActivitySource::factory()
            ->for($activity)
            ->for($connection, 'connection')
            ->create([
                'source_file_path' => $path,
                'source_file_checksum' => hash('sha256', $contents),
            ]);
    }

    /** @param array<string, mixed> $result */
    private function parserReturning(array $result): ActivitySourceFileParser
    {
        return new class($result) implements ActivitySourceFileParser
        {
            /** @param array<string, mixed> $result */
            public function __construct(private readonly array $result) {}

            public function parse(string $path): array
            {
                return $this->result;
            }
        };
    }
}
