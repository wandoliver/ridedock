<?php

namespace Tests\Feature;

use App\Domain\Activities\Contracts\ActivityFileProvider;
use App\Domain\Activities\Data\ProviderActivity;
use App\Domain\Activities\Data\ProviderPage;
use App\Domain\Activities\Enums\ProviderName;
use App\Domain\Activities\Services\ActivityFileArchiver;
use App\Domain\Activities\Services\ActivityImporter;
use App\Domain\Activities\Services\ProviderRegistry;
use App\Domain\Activities\Services\ProviderTokenManager;
use App\Domain\Activities\Services\SyncProviderActivities;
use App\Jobs\EnrichActivitySource;
use App\Models\ActivitySource;
use App\Models\ProviderConnection;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SyncProviderActivitiesTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_bulk_sync_archives_fit_and_queues_stream_enrichment(): void
    {
        Queue::fake();
        Storage::fake('activity_sources');
        config()->set('activity-providers.sync.source_file_disk', 'activity_sources');
        config()->set('activity-providers.sync.store_source_files', true);
        $user = User::factory()->create();
        $connection = ProviderConnection::factory()
            ->for($user)
            ->hammerhead()
            ->create(['token_expires_at' => now()->addHour()]);
        $provider = $this->provider();
        $providers = new ProviderRegistry([$provider]);
        $sync = new SyncProviderActivities(
            $providers,
            new ProviderTokenManager($providers),
            app(ActivityImporter::class),
            app(ActivityFileArchiver::class),
        );

        $imported = $sync->handle($connection);

        $this->assertSame(1, $imported);
        $source = ActivitySource::query()->firstOrFail();
        $this->assertNotNull($source->source_file_path);
        Storage::disk('activity_sources')->assertExists($source->source_file_path);
        Queue::assertPushed(
            EnrichActivitySource::class,
            fn (EnrichActivitySource $job): bool => $job->activitySource->is($source),
        );
    }

    private function provider(): ActivityFileProvider
    {
        return new class implements ActivityFileProvider
        {
            public function key(): ProviderName
            {
                return ProviderName::Hammerhead;
            }

            public function capabilities(): array
            {
                return ['activities', 'activity_fit'];
            }

            public function activities(
                ProviderConnection $connection,
                ?CarbonImmutable $since = null,
                ?string $cursor = null,
            ): ProviderPage {
                return new ProviderPage([
                    new ProviderActivity(
                        externalId: 'hammerhead-ride',
                        name: 'Imported ride',
                        type: 'ride',
                        startedAt: CarbonImmutable::parse('2026-08-30T06:00:00Z'),
                    ),
                ], null);
            }

            public function activity(ProviderConnection $connection, string $externalId): ProviderActivity
            {
                throw new \LogicException('Not used during bulk sync.');
            }

            public function downloadActivityFile(ProviderConnection $connection, string $externalId): string
            {
                return 'FIT contents';
            }

            public function activityFileExtension(): string
            {
                return 'fit';
            }
        };
    }
}
