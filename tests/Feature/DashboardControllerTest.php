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

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_guest_sees_landing_page_with_login_and_no_private_dashboard(): void
    {
        Activity::factory()->create(['name' => 'Private morning ride']);

        $this->withoutVite()
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Understand your rides. Follow your plan. Own your data.')
            ->assertSee('Sign in')
            ->assertSee(route('login.store'), false)
            ->assertSee('name="email"', false)
            ->assertSee('name="password"', false)
            ->assertDontSee('Metrics &amp; heatmap', false)
            ->assertDontSee('Interactive heatmap of your routes')
            ->assertDontSee('Private morning ride');
    }

    public function test_overview_is_limited_to_three_recent_rides_and_the_current_week(): void
    {
        Carbon::setTestNow('2026-10-05 12:00:00');
        $user = User::factory()->create();
        foreach (range(1, 4) as $day) {
            Activity::factory()->for($user)->create(['name' => 'Ride '.$day, 'started_at' => '2026-10-0'.$day.'T10:00:00Z']);
        }
        Activity::factory()->for($user)->create(['name' => 'Today ride', 'started_at' => now(), 'distance_m' => 42500]);
        Activity::factory()->create(['name' => 'Private ride']);

        $this->withoutVite()->actingAs($user)->get(route('dashboard'))->assertOk()
            ->assertSee('Today ride')->assertSee('Ride 4')->assertSee('Ride 3')
            ->assertDontSee('Ride 2')->assertDontSee('Private ride')
            ->assertSee('42,5 km')->assertSee('Deine Woche')
            ->assertSee(route('analytics'), false)->assertSee(route('activities.index'), false)
            ->assertDontSee('id="ride-heatmap"', false)->assertDontSee('data-stream-chart', false);
    }

    public function test_insights_show_only_the_owners_metrics_and_routes(): void
    {
        config()->set('heatmap.privacy_trim_m', 0);
        $user = User::factory()->create();
        $activity = Activity::factory()->for($user)->create([
            'distance_m' => 42500, 'moving_time_s' => 5400, 'elevation_gain_m' => 620,
        ]);
        ActivityStream::factory()->for($activity)->create([
            'type' => 'latlng', 'data' => [['value' => [0.01, 0.02]], ['value' => [0.011, 0.021]]],
        ]);
        $private = Activity::factory()->create(['distance_m' => 900000]);

        $this->withoutVite()->actingAs($user)->get(route('analytics'))->assertOk()
            ->assertSee('43 km')->assertSee('620 m')->assertSee('1 Route')
            ->assertSee($activity->id)->assertSee('data-heatmap-expand', false)
            ->assertDontSee($private->id)->assertDontSee('900 km');
    }

    public function test_current_year_is_default_and_period_filter_updates_totals_and_routes(): void
    {
        config()->set('heatmap.privacy_trim_m', 0);
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
            'data' => [['value' => [0.0, 0.0]], ['value' => [0.001, 0.001]]],
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
            'data' => [['value' => [0.0, 0.0]], ['value' => [0.001, 0.001]]],
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
            'data' => [['value' => [0.0, 0.0]], ['value' => [0.001, 0.001]]],
        ]);

        $this->withoutVite()
            ->actingAs($user)
            ->get(route('analytics'))
            ->assertOk()
            ->assertSee('Jahr 2026')
            ->assertSee('50 km')
            ->assertSee($currentYear->id)
            ->assertSee($recent->id)
            ->assertSee('2 Routen')
            ->assertDontSee($lastYear->id)
            ->assertDontSee('110 km');

        $this->withoutVite()
            ->actingAs($user)
            ->get(route('analytics', ['period' => '30d']))
            ->assertOk()
            ->assertSee('Letzte 30 Tage')
            ->assertSee('10 km')
            ->assertSee($recent->id)
            ->assertSee('1 Route')
            ->assertDontSee($currentYear->id)
            ->assertDontSee($lastYear->id);

        $this->withoutVite()
            ->actingAs($user)
            ->get(route('analytics', ['period' => 'all']))
            ->assertOk()
            ->assertSee('Gesamter Zeitraum')
            ->assertSee('110 km')
            ->assertSee('3 Routen')
            ->assertSee($lastYear->id);
    }

    public function test_invalid_period_falls_back_to_current_year(): void
    {
        Carbon::setTestNow('2026-08-30 12:00:00');
        $user = User::factory()->create();

        $this->withoutVite()
            ->actingAs($user)
            ->get(route('analytics', ['period' => 'invalid']))
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
    public function test_training_overview_shows_coverage_and_is_independent_of_the_archive_filter(): void
    {
        Carbon::setTestNow('2026-10-02 12:00:00');
        $user = User::factory()->create();
        Activity::factory()->for($user)->create([
            'started_at' => '2026-09-30T10:00:00Z', 'moving_time_s' => 3600, 'rpe' => null,
        ]);
        Activity::factory()->for($user)->create([
            'started_at' => '2026-09-29T10:00:00Z', 'moving_time_s' => 1800, 'rpe' => 4,
        ]);
        Activity::factory()->create([
            'started_at' => '2026-09-29T10:00:00Z', 'moving_time_s' => 99999, 'rpe' => 10,
        ]);
        foreach (['30d', 'year', 'all'] as $period) {
            $this->withoutVite()->actingAs($user)->get(route('analytics', ['period' => $period]))
                ->assertOk()->assertSee('Trainingsauswertung')->assertSee('Anstrengungsverteilung')
                ->assertSee('1 von 2 Fahrten mit RPE und Fahrzeit')
                ->assertSee('33% der bekannten Fahrzeit bewertet')
                ->assertSee('Wochen im Detail')->assertSee('Wie wird das berechnet?')
                ->assertViewHas('trainingStatistics', fn ($stats) => $stats['summary']['activity_count'] === 2
                    && $stats['summary']['session_rpe_load'] === 120.0);
        }
    }

    public function test_training_overview_has_an_english_empty_state(): void
    {
        $user = User::factory()->create(['locale' => 'en']);
        $this->withoutVite()->actingAs($user)->get(route('analytics'))->assertOk()
            ->assertSee('Training overview')->assertSee('No rides have been recorded in the last six weeks yet.')
            ->assertDontSee('training.title')->assertDontSee('Trainingsauswertung');
    }

}
