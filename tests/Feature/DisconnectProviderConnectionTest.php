<?php

namespace Tests\Feature;

use App\Domain\Activities\Data\ProviderToken;
use App\Domain\Activities\Enums\ConnectionStatus;
use App\Domain\Activities\Services\DisconnectProviderConnection;
use App\Domain\Activities\Services\ProviderTokenManager;
use App\Domain\Activities\Services\SyncProviderActivities;
use App\Jobs\ImportProviderActivity;
use App\Models\ActivitySource;
use App\Models\ProviderConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DisconnectProviderConnectionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public static function providers(): array
    {
        return [['hammerhead'], ['wahoo']];
    }

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Http::fake(['api.wahooligan.com/v1/permissions' => Http::response('', 204)]);
    }

    #[DataProvider('providers')]
    public function test_disconnect_clears_credentials_preserves_rides_and_allows_reconnect(string $provider): void
    {
        config()->set("activity-providers.$provider.enabled", true);
        $connection = ProviderConnection::factory()->create(['provider' => $provider, 'metadata' => ['secret' => 'private']]);
        $source = ActivitySource::factory()->create(['provider_connection_id' => $connection->id]);
        $this->actingAs($connection->user)->delete(route('provider.disconnect', $provider))
            ->assertRedirect(route('settings.connections'));
        $connection->refresh();
        $this->assertSame(ConnectionStatus::Revoked, $connection->status);
        foreach (['access_token', 'refresh_token', 'external_user_id', 'token_expires_at', 'scopes', 'capabilities', 'metadata', 'sync_cursor', 'last_error'] as $field) {
            $this->assertNull(DB::table('provider_connections')->where('id', $connection->id)->value($field), $field);
        }
        $this->assertModelExists($source);
        $this->assertModelExists($source->activity);
        $this->withoutVite()->get(route('settings.connections'))->assertOk()
            ->assertSee('0 verbunden')->assertSee('Nicht verbunden')
            ->assertSee(route('provider.connect', $provider), false)
            ->assertDontSee(route('provider.sync', $provider), false)
            ->assertDontSee('action="'.route('provider.disconnect', $provider).'"', false);
        if ($provider === 'wahoo') {
            Http::assertSent(fn ($request) => $request->method() === 'DELETE'
                && $request->url() === 'https://api.wahooligan.com/v1/permissions');
        } else {
            Http::assertNothingSent();
        }
        // Repeating a disconnect is harmless and sends no further provider request.
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake();
        $this->delete(route('provider.disconnect', $provider))->assertRedirect();
        Http::assertNothingSent();
    }

    public function test_wahoo_failure_still_clears_local_credentials_without_logging_provider_details(): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake(['*' => Http::response('sensitive provider error', 500)]);
        $connection = ProviderConnection::factory()->create(['provider' => 'wahoo']);
        $this->actingAs($connection->user)->delete(route('provider.disconnect', 'wahoo'))
            ->assertRedirect()->assertSessionHas('status', fn ($message) => str_contains($message, 'lokal getrennt'));
        $this->assertNull($connection->fresh()->access_token);
        $this->assertNull($connection->fresh()->last_error);
    }

    public function test_guest_and_other_users_cannot_disconnect_a_connection(): void
    {
        $connection = ProviderConnection::factory()->create(['provider' => 'wahoo']);
        $this->delete(route('provider.disconnect', 'wahoo'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->delete(route('provider.disconnect', 'wahoo'))->assertNotFound();
        $this->assertNotNull($connection->fresh()->access_token);
        Http::assertNothingSent();
    }

    public function test_api_requires_write_ability_and_returns_disconnected_status(): void
    {
        $connection = ProviderConnection::factory()->create(['provider' => 'wahoo']);
        Sanctum::actingAs($connection->user, ['activities:read']);
        $this->deleteJson('/api/v1/connections/wahoo')->assertForbidden();
        Sanctum::actingAs($connection->user, ['connections:write']);
        $this->deleteJson('/api/v1/connections/wahoo')->assertOk()->assertJson(['status' => 'disconnected']);
    }

    public function test_disconnected_connections_skip_jobs_and_reject_sync_and_stale_token_writes(): void
    {
        $connection = ProviderConnection::factory()->create(['provider' => 'wahoo']);
        $stale = clone $connection;
        app(DisconnectProviderConnection::class)->handle($connection);
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake();
        Queue::fake();
        $this->actingAs($connection->user)->postJson(route('provider.sync', 'wahoo'))->assertConflict();
        $this->assertSame(0, app(SyncProviderActivities::class)->handle($stale));
        app()->call([new ImportProviderActivity($stale, 'old-ride'), 'handle']);
        app(ProviderTokenManager::class)->persist($stale, new ProviderToken('late-token', 'late-refresh', null, 'old-user'));
        $this->assertSame(ConnectionStatus::Revoked, $connection->fresh()->status);
        $this->assertNull($connection->fresh()->access_token);
        Http::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_disconnect_during_sync_does_not_restore_status_or_import_the_response(): void
    {
        config()->set('activity-providers.wahoo.enabled', true);
        $connection = ProviderConnection::factory()->create(['provider' => 'wahoo', 'token_expires_at' => null]);
        Http::fake([
            'api.wahooligan.com/v1/workouts*' => function () use ($connection) {
                app(DisconnectProviderConnection::class)->handle($connection->fresh());

                return Http::response(['workouts' => [[
                    'id' => 1, 'starts' => '2026-09-01T08:00:00Z', 'workout_type_id' => 0,
                    'workout_summary' => ['name' => 'Late ride', 'duration_active_accum' => 3600],
                ]], 'total' => 100, 'per_page' => 50]);
            },
        ]);
        $this->assertSame(0, app(SyncProviderActivities::class)->handle($connection));
        $this->assertSame(ConnectionStatus::Revoked, $connection->fresh()->status);
        $this->assertNull($connection->fresh()->access_token);
        $this->assertDatabaseEmpty('activity_sources');
        Http::assertSentCount(2);
    }

    public function test_token_refresh_still_persists_encrypted_credentials_for_connected_accounts(): void
    {
        $connection = ProviderConnection::factory()->create(['provider' => 'wahoo']);
        app(ProviderTokenManager::class)->persist($connection, new ProviderToken('new-access', 'new-refresh', null, 'user'));
        $this->assertSame('new-access', $connection->fresh()->access_token);
        $this->assertSame('new-refresh', $connection->fresh()->refresh_token);
        $this->assertNotSame('new-access', $connection->getRawOriginal('access_token'));
        $this->assertTrue($connection->stillConnected());
    }

    public function test_disconnect_is_visible_for_connected_syncing_and_failed_connections(): void
    {
        $connection = ProviderConnection::factory()->create(['provider' => 'wahoo']);
        foreach ([ConnectionStatus::Active, ConnectionStatus::Syncing, ConnectionStatus::Error] as $status) {
            $connection->update(['status' => $status]);
            $this->withoutVite()->actingAs($connection->user)->get(route('settings.connections'))
                ->assertOk()->assertSee('Verbindung trennen')
                ->assertSee('action="'.route('provider.disconnect', 'wahoo').'"', false);
        }
    }

    public function test_wahoo_oauth_reconnects_the_same_record(): void
    {
        Queue::fake();
        config()->set('activity-providers.wahoo', [
            ...config('activity-providers.wahoo'),
            'enabled' => true,
            'client_id' => 'wahoo-client',
            'client_secret' => 'wahoo-secret',
            'redirect_uri' => 'http://localhost/api/v1/oauth/wahoo/callback',
            'api_url' => 'https://api.wahooligan.com/v1',
            'auth_url' => 'https://api.wahooligan.com',
        ]);
        $connection = ProviderConnection::factory()->create(['provider' => 'wahoo']);
        app(DisconnectProviderConnection::class)->handle($connection);
        Cache::put('provider-oauth-state:reconnect', ['user_id' => $connection->user_id, 'provider' => 'wahoo']);
        Http::fake([
            'api.wahooligan.com/oauth/token' => Http::response(['access_token' => 'new-access', 'refresh_token' => 'new-refresh', 'expires_in' => 3600]),
            'api.wahooligan.com/v1/user' => Http::response(['id' => 123]),
        ]);
        $this->get('/api/v1/oauth/wahoo/callback?code=new-code&state=reconnect')->assertRedirect(route('settings.connections'));
        $this->assertDatabaseCount('provider_connections', 1);
        $this->assertSame('new-access', $connection->fresh()->access_token);
        $this->assertSame(ConnectionStatus::Active, $connection->fresh()->status);
    }
}
