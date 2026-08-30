<?php

namespace Tests\Feature;

use App\Jobs\SyncProviderConnection;
use App\Models\ProviderConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ProviderOAuthControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('activity-providers.hammerhead', [
            ...config('activity-providers.hammerhead'),
            'enabled' => true,
            'client_id' => 'hammerhead-client',
            'client_secret' => 'hammerhead-secret',
            'redirect_uri' => 'http://127.0.0.1:8080/api/v1/oauth/hammerhead/callback',
        ]);
    }

    public function test_authenticated_user_starts_hammerhead_authorization(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('provider.connect', 'hammerhead'));

        $response->assertRedirect();
        $location = $response->headers->get('Location');
        $this->assertIsString($location);
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        $this->assertSame('hammerhead-client', $query['client_id']);
        $this->assertSame(config('activity-providers.hammerhead.redirect_uri'), $query['redirect_uri']);
        $this->assertSame('code', $query['response_type']);
        $this->assertSame('activity:read', $query['scope']);
        $this->assertSame([
            'user_id' => $user->id,
            'provider' => 'hammerhead',
        ], Cache::get('provider-oauth-state:'.$query['state']));
    }

    public function test_callback_stores_connection_starts_sync_and_returns_to_connection_settings(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        Cache::put('provider-oauth-state:valid-state', [
            'user_id' => $user->id,
            'provider' => 'hammerhead',
        ]);
        Http::fake([
            'api.hammerhead.io/v1/auth/oauth/token' => Http::response([
                'access_token' => 'access-token',
                'refresh_token' => 'refresh-token',
                'expires_in' => 3600,
                'user_id' => 'hammerhead-user',
            ]),
        ]);

        $response = $this->get('/api/v1/oauth/hammerhead/callback?code=valid-code&state=valid-state');

        $response->assertRedirect(route('settings.connections'))
            ->assertSessionHas('status', 'Hammerhead ist verbunden. Die erste Synchronisierung wurde angestoßen.');
        $connection = ProviderConnection::query()->firstOrFail();
        $this->assertSame($user->id, $connection->user_id);
        $this->assertSame('hammerhead-user', $connection->external_user_id);
        $this->assertSame('access-token', $connection->access_token);
        Queue::assertPushed(SyncProviderConnection::class, fn (SyncProviderConnection $job): bool => $job->providerConnection->is($connection));
        $this->assertNull(Cache::get('provider-oauth-state:valid-state'));
    }

    public function test_callback_rejects_an_invalid_state_without_calling_hammerhead(): void
    {
        Http::fake();

        $this->get('/api/v1/oauth/hammerhead/callback?code=valid-code&state=invalid-state')
            ->assertBadRequest();

        Http::assertNothingSent();
        $this->assertDatabaseEmpty('provider_connections');
    }

    public function test_owner_restarts_a_provider_sync_from_connection_settings(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $connection = ProviderConnection::factory()->for($user)->hammerhead()->create();

        $this->actingAs($user)
            ->post(route('provider.sync', 'hammerhead'))
            ->assertRedirect(route('settings.connections'))
            ->assertSessionHas('status', 'Hammerhead Synchronisierung wurde angestoßen.');

        Queue::assertPushed(SyncProviderConnection::class, fn (SyncProviderConnection $job): bool => $job->providerConnection->is($connection));
    }

    public function test_sync_rejects_an_unknown_provider(): void
    {
        Queue::fake();

        $this->actingAs(User::factory()->create())
            ->post(route('provider.sync', 'wahoo'))
            ->assertNotFound();

        Queue::assertNothingPushed();
    }
}
