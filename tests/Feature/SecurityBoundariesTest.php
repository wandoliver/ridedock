<?php

namespace Tests\Feature;

use App\Domain\Activities\Contracts\ActivityProvider;
use App\Domain\Activities\Contracts\ActivityFileProvider;
use App\Domain\Activities\Enums\ConnectionStatus;
use App\Domain\Activities\Enums\ProviderName;
use App\Domain\Activities\Services\ActivityFileArchiver;
use App\Domain\Activities\Services\ActivityImporter;
use App\Domain\Activities\Services\ProviderRegistry;
use App\Domain\Activities\Services\ProviderTokenManager;
use App\Domain\Activities\Services\SyncProviderActivities;
use App\Models\ProviderConnection;
use App\Models\ActivitySource;
use App\Models\User;
use App\Infrastructure\Ai\OpenAiTrainingAdvisor;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class SecurityBoundariesTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_sync_failures_do_not_persist_or_rethrow_remote_response_details(): void
    {
        $connection = ProviderConnection::factory()->create(['token_expires_at' => null]);
        $provider = Mockery::mock(ActivityProvider::class);
        $provider->shouldReceive('key')->andReturn(ProviderName::Hammerhead);
        $provider->shouldReceive('activities')->once()->andThrow(new RuntimeException('PRIVATE_TOKEN_AND_REMOTE_BODY'));
        $registry = new ProviderRegistry([$provider]);
        $sync = new SyncProviderActivities($registry, new ProviderTokenManager($registry), app(ActivityImporter::class), app(ActivityFileArchiver::class));
        try {
            $sync->handle($connection);
            $this->fail('Synchronization must fail.');
        } catch (RuntimeException $exception) {
            $this->assertStringNotContainsString('PRIVATE_TOKEN_AND_REMOTE_BODY', $exception->getMessage());
            $this->assertNull($exception->getPrevious());
        }
        $connection->refresh();
        $this->assertSame(ConnectionStatus::Error, $connection->status);
        $this->assertStringNotContainsString('PRIVATE_TOKEN_AND_REMOTE_BODY', $connection->last_error);
    }

    public function test_connection_api_does_not_expose_legacy_error_details_or_tokens(): void
    {
        $connection = ProviderConnection::factory()->create(['last_error' => 'PRIVATE_LEGACY_ERROR']);
        Sanctum::actingAs($connection->user, ['connections:write']);
        $response = $this->getJson('/api/v1/connections')->assertOk();
        $response->assertJsonMissingPath('data.0.access_token')->assertJsonMissingPath('data.0.refresh_token');
        $this->assertStringNotContainsString('PRIVATE_LEGACY_ERROR', $response->getContent());
    }

    public function test_ai_http_failures_expose_only_status_without_response_details(): void
    {
        Http::fake(['api.openai.com/v1/responses' => Http::response('PRIVATE_REMOTE_AI_BODY', 502)]);
        $advisor = new OpenAiTrainingAdvisor(Http::getFacadeRoot(), 'synthetic-key', 'test-model', 'https://api.openai.com/v1', 10);
        try {
            $advisor->analyzeActivity([]);
            $this->fail('AI request must fail.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('502', $exception->getMessage());
            $this->assertStringNotContainsString('PRIVATE_REMOTE_AI_BODY', $exception->getMessage());
            $this->assertNull($exception->getPrevious());
        }
    }

    public function test_production_seeding_does_not_create_a_default_account(): void
    {
        $this->assertDatabaseEmpty('users');
        $this->app->instance('env', 'production');
        $this->app->make(DatabaseSeeder::class)->run();
        $this->assertSame(0, User::query()->count());
    }

    public function test_archiving_refuses_a_public_disk_before_downloading(): void
    {
        config()->set('activity-providers.sync.source_file_disk', 'public');
        $provider = Mockery::mock(ActivityFileProvider::class);
        $provider->shouldNotReceive('downloadActivityFile');
        $connection = ProviderConnection::factory()->create();
        $source = ActivitySource::factory()->for($connection, 'connection')->create();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('private non-serving disk');
        app(ActivityFileArchiver::class)->archive($provider, $connection, $source);
    }

    public function test_archiving_refuses_oversized_files_before_writing_them(): void
    {
        config()->set('activity-providers.fit.max_file_bytes', 3);
        $provider = Mockery::mock(ActivityFileProvider::class);
        $provider->shouldReceive('downloadActivityFile')->once()->andReturn('TOO LARGE');
        $provider->shouldNotReceive('activityFileExtension');
        $connection = ProviderConnection::factory()->create();
        $source = ActivitySource::factory()->for($connection, 'connection')->create();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('size limit');
        app(ActivityFileArchiver::class)->archive($provider, $connection, $source);
    }
}
