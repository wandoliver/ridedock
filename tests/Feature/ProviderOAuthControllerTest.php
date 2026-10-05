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
        config()->set('activity-providers.wahoo', [
            ...config('activity-providers.wahoo'),
            'enabled' => true,
            'client_id' => 'wahoo-client',
            'client_secret' => 'wahoo-secret',
            'redirect_uri' => 'http://127.0.0.1:8080/api/v1/oauth/wahoo/callback',
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
            'session_hash' => hash('sha256', session()->getId()),
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

        $response = $this->actingAs($user)->get('/api/v1/oauth/hammerhead/callback?code=valid-code&state=valid-state');

        $response->assertRedirect(route('settings.connections'))
            ->assertSessionHas('status', 'Hammerhead ist verbunden. Die erste Synchronisierung wurde angestoßen.');
        $connection = ProviderConnection::query()->firstOrFail();
        $this->assertSame($user->id, $connection->user_id);
        $this->assertSame('hammerhead-user', $connection->external_user_id);
        $this->assertSame('access-token', $connection->access_token);
        Queue::assertPushed(SyncProviderConnection::class, fn (SyncProviderConnection $job): bool => $job->providerConnection->is($connection));
        $this->assertNull(Cache::get('provider-oauth-state:valid-state'));
        $this->get('/api/v1/oauth/hammerhead/callback?code=valid-code&state=valid-state')->assertBadRequest();
        Http::assertSentCount(1);
    }

    public function test_authenticated_user_connects_wahoo_and_stores_the_external_user(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $authorization = $this->actingAs($user)->get(route('provider.connect', 'wahoo'));
        $authorization->assertRedirect();
        $location = $authorization->headers->get('Location');
        $this->assertIsString($location);
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $this->assertSame('wahoo-client', $query['client_id']);
        $this->assertSame('user_read workouts_read offline_data', $query['scope']);
        $this->withCookie(config('session.cookie'), session()->getId());

        Http::fake([
            'api.wahooligan.com/oauth/token' => Http::response([
                'access_token' => 'wahoo-access-token',
                'refresh_token' => 'wahoo-refresh-token',
                'expires_in' => 7200,
            ]),
            'api.wahooligan.com/v1/user' => Http::response(['id' => 60462]),
        ]);

        $response = $this->get('/api/v1/oauth/wahoo/callback?code=valid-code&state='.$query['state']);

        $response->assertRedirect(route('settings.connections'))
            ->assertSessionHas('status', 'Wahoo ist verbunden. Die erste Synchronisierung wurde angestoßen.');
        $connection = ProviderConnection::query()->where('provider', 'wahoo')->firstOrFail();
        $this->assertSame('60462', $connection->external_user_id);
        $this->assertSame('wahoo-access-token', $connection->access_token);
        $this->assertSame(['user_read', 'workouts_read', 'offline_data'], $connection->scopes);
        Queue::assertPushed(SyncProviderConnection::class);
    }

    public function test_callback_rejects_an_invalid_state_without_calling_hammerhead(): void
    {
        Http::fake();

        $this->actingAs(User::factory()->create())->get('/api/v1/oauth/hammerhead/callback?code=valid-code&state=invalid-state')
            ->assertBadRequest();

        Http::assertNothingSent();
        $this->assertDatabaseEmpty('provider_connections');
    }

    public function test_oauth_callback_requires_an_authenticated_browser(): void
    {
        $this->getJson('/api/v1/oauth/hammerhead/callback?code=valid-code&state=anything')->assertUnauthorized();
        Http::assertNothingSent();
    }

    public function test_another_user_cannot_consume_an_oauth_state(): void
    {
        $owner = User::factory()->create();
        Cache::put('provider-oauth-state:owned-state', ['user_id' => $owner->id, 'provider' => 'hammerhead']);
        $this->actingAs(User::factory()->create())
            ->get('/api/v1/oauth/hammerhead/callback?code=valid-code&state=owned-state')->assertBadRequest();
        $this->assertNotNull(Cache::get('provider-oauth-state:owned-state'));
        $this->assertDatabaseEmpty('provider_connections');
        Http::assertNothingSent();
    }

    public function test_web_oauth_state_cannot_be_used_from_a_different_session(): void
    {
        $user = User::factory()->create();
        Cache::put('provider-oauth-state:other-session', [
            'user_id' => $user->id, 'provider' => 'hammerhead', 'session_hash' => hash('sha256', 'another-browser-session'),
        ]);
        $this->actingAs($user)
            ->get('/api/v1/oauth/hammerhead/callback?code=valid-code&state=other-session')->assertBadRequest();
        $this->assertDatabaseEmpty('provider_connections');
        Http::assertNothingSent();
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
            ->post(route('provider.sync', 'garmin'))
            ->assertNotFound();

        Queue::assertNothingPushed();
    }
}
