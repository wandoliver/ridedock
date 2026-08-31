<?php

namespace Tests\Feature;

use App\Domain\Activities\Enums\ConnectionStatus;
use App\Models\ProviderConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ConnectionSettingsControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('settings.connections'))
            ->assertRedirect(route('login'));
    }

    public function test_user_sees_only_their_connection_status(): void
    {
        config()->set('activity-providers.hammerhead.enabled', true);
        $user = User::factory()->create();
        $connection = ProviderConnection::factory()
            ->for($user)
            ->hammerhead()
            ->create(['last_synced_at' => now()->subHour()]);
        ProviderConnection::factory()->create(['status' => ConnectionStatus::Error]);

        $this->withoutVite()
            ->actingAs($user)
            ->get(route('settings.connections'))
            ->assertOk()
            ->assertViewHas('connections', fn (Collection $connections): bool => $connections->count() === 1
                && $connections->first()->is($connection))
            ->assertSee('Aktivitätsdienste')
            ->assertSee('Hammerhead')
            ->assertSee('Zuletzt synchronisiert vor 1 Stunde')
            ->assertSee('Jetzt synchronisieren')
            ->assertSee('data-provider-sync-form', false)
            ->assertSee('data-provider-sync-progress', false)
            ->assertSee('Wahoo')
            ->assertSee('Nicht konfiguriert')
            ->assertDontSee('Hammerhead ist die primäre Quelle.')
            ->assertDontSee('Synchronisierung fehlgeschlagen');
    }

    public function test_configured_wahoo_can_be_connected(): void
    {
        config()->set('activity-providers.wahoo.enabled', true);

        $this->withoutVite()
            ->actingAs(User::factory()->create())
            ->get(route('settings.connections'))
            ->assertOk()
            ->assertSee('Wahoo')
            ->assertSee(route('provider.connect', 'wahoo'), false);
    }

    public function test_failed_connection_offers_retry(): void
    {
        $user = User::factory()->create();
        ProviderConnection::factory()
            ->for($user)
            ->hammerhead()
            ->create(['status' => ConnectionStatus::Error]);

        $this->withoutVite()
            ->actingAs($user)
            ->get(route('settings.connections'))
            ->assertOk()
            ->assertSee('Synchronisierung fehlgeschlagen')
            ->assertSee('Erneut versuchen')
            ->assertSee(route('provider.sync', 'hammerhead'), false);
    }
}
