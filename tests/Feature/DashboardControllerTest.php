<?php

namespace Tests\Feature;

use App\Domain\Activities\Enums\ConnectionStatus;
use App\Models\Activity;
use App\Models\ActivityStream;
use App\Models\ProviderConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_sees_landing_page_with_login_and_no_private_dashboard(): void
    {
        Activity::factory()->create(['name' => 'Private morning ride']);

        $this->withoutVite()
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Fahrten verstehen, Daten behalten.')
            ->assertSee('Anmelden')
            ->assertSee(route('login.store'), false)
            ->assertSee('name="email"', false)
            ->assertSee('name="password"', false)
            ->assertDontSee('Kennzahlen &amp; Heatmap', false)
            ->assertDontSee('Interaktive Heatmap deiner Routen')
            ->assertDontSee('Private morning ride');
    }

    public function test_authenticated_user_sees_only_their_totals_and_recent_activities(): void
    {
        $user = User::factory()->create();
        $activity = Activity::factory()->for($user)->create([
            'name' => 'Passo Ride',
            'distance_m' => 42500,
            'moving_time_s' => 5400,
            'elevation_gain_m' => 620,
        ]);
        ActivityStream::factory()->for($activity)->create([
            'type' => 'latlng',
            'data' => [
                ['value' => [46.4983, 11.3548]],
                ['value' => [46.4990, 11.3555]],
            ],
        ]);
        Activity::factory()->create(['name' => 'Someone else ride', 'distance_m' => 900000]);

        $this->withoutVite()
            ->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Passo Ride')
            ->assertSee('43 km')
            ->assertSee('620 m')
            ->assertSee('1 Route')
            ->assertSee($activity->id)
            ->assertSee('data-heatmap-expand', false)
            ->assertSee(route('settings.connections'), false)
            ->assertDontSee('Fahrten verstehen, Daten behalten.')
            ->assertDontSee(route('login.store'), false)
            ->assertDontSee('Deine Wege wachsen mit.')
            ->assertDontSee('Datenquellen')
            ->assertDontSee('Someone else ride')
            ->assertDontSee('900 km');
    }

    public function test_current_year_is_default_and_period_filter_updates_totals_and_routes(): void
    {
        Carbon::setTestNow('2026-08-30 12:00:00');
        $user = User::factory()->create();
        $currentYear = Activity::factory()->for($user)->create([
            'name' => 'Current year ride',
            'started_at' => '2026-01-15T10:00:00Z',
            'distance_m' => 40000,
            'moving_time_s' => 3600,
            'elevation_gain_m' => 500,
        ]);
        ActivityStream::factory()->for($currentYear)->create([
            'type' => 'latlng',
            'data' => [['value' => [46.0, 11.0]], ['value' => [46.1, 11.1]]],
        ]);
        $recent = Activity::factory()->for($user)->create([
            'name' => 'Recent ride',
            'started_at' => '2026-08-20T10:00:00Z',
            'distance_m' => 10000,
            'moving_time_s' => 1800,
            'elevation_gain_m' => 100,
        ]);
        ActivityStream::factory()->for($recent)->create([
            'type' => 'latlng',
            'data' => [['value' => [46.0, 11.0]], ['value' => [46.1, 11.1]]],
        ]);
        $lastYear = Activity::factory()->for($user)->create([
            'name' => 'Last year ride',
            'started_at' => '2025-12-15T10:00:00Z',
            'distance_m' => 60000,
            'moving_time_s' => 7200,
            'elevation_gain_m' => 700,
        ]);
        ActivityStream::factory()->for($lastYear)->create([
            'type' => 'latlng',
            'data' => [['value' => [46.0, 11.0]], ['value' => [46.1, 11.1]]],
        ]);

        $this->withoutVite()
            ->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Jahr 2026')
            ->assertSee('50 km')
            ->assertSee('Current year ride')
            ->assertSee('Recent ride')
            ->assertSee('2 Routen')
            ->assertDontSee('Last year ride')
            ->assertDontSee('110 km');

        $this->withoutVite()
            ->actingAs($user)
            ->get(route('dashboard', ['period' => '30d']))
            ->assertOk()
            ->assertSee('Letzte 30 Tage')
            ->assertSee('10 km')
            ->assertSee('Recent ride')
            ->assertSee('1 Route')
            ->assertDontSee('Current year ride')
            ->assertDontSee('Last year ride');

        $this->withoutVite()
            ->actingAs($user)
            ->get(route('dashboard', ['period' => 'all']))
            ->assertOk()
            ->assertSee('Gesamter Zeitraum')
            ->assertSee('110 km')
            ->assertSee('3 Routen')
            ->assertSee('Last year ride');
    }

    public function test_invalid_period_falls_back_to_current_year(): void
    {
        Carbon::setTestNow('2026-08-30 12:00:00');
        $user = User::factory()->create();

        $this->withoutVite()
            ->actingAs($user)
            ->get(route('dashboard', ['period' => 'invalid']))
            ->assertOk()
            ->assertSee('Jahr 2026');
    }

    public function test_failed_connection_is_reduced_to_a_settings_warning(): void
    {
        $user = User::factory()->create();
        ProviderConnection::factory()
            ->for($user)
            ->hammerhead()
            ->create(['status' => ConnectionStatus::Error]);

        $this->withoutVite()
            ->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Eine Datenquelle benötigt Aufmerksamkeit.')
            ->assertSee('Verbindungen prüfen')
            ->assertSee(route('settings.connections'), false)
            ->assertDontSee('Synchronisierung fehlgeschlagen');
    }
}
