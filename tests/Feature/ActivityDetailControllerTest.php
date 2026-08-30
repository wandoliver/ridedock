<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ActivityEvent;
use App\Models\ActivitySource;
use App\Models\ActivityStream;
use App\Models\ProviderConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ActivityDetailControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_owner_sees_activity_metrics_streams_sources_and_events(): void
    {
        $user = User::factory()->create();
        $activity = Activity::factory()->for($user)->create([
            'name' => 'Passo Mendola',
            'started_at' => '2026-08-30T06:00:00Z',
            'distance_m' => 42500,
            'moving_time_s' => 5400,
            'elevation_gain_m' => 620,
            'average_power' => 220,
            'rpe' => 6,
            'notes' => 'Kontrolliert gefahren.',
        ]);
        $connection = ProviderConnection::factory()->for($user)->create();
        ActivitySource::factory()
            ->for($activity)
            ->for($connection, 'connection')
            ->create();
        ActivityStream::factory()->for($activity)->create([
            'type' => 'latlng',
            'data' => [
                ['elapsed_seconds' => 0, 'value' => [46.4983, 11.3548]],
                ['elapsed_seconds' => 10, 'value' => [46.4990, 11.3555]],
            ],
        ]);
        ActivityStream::factory()->for($activity)->create([
            'type' => 'power',
            'data' => [
                ['elapsed_seconds' => 0, 'value' => 205],
                ['elapsed_seconds' => 10, 'value' => 235],
            ],
        ]);
        ActivityEvent::factory()->for($activity)->create([
            'type' => 'shift',
            'elapsed_seconds' => 10,
        ]);

        $response = $this->withoutVite()
            ->actingAs($user)
            ->get(route('activities.show', $activity));

        $response->assertOk()
            ->assertViewHas('activity', fn (Activity $viewActivity): bool => $viewActivity->is($activity))
            ->assertViewHas('activityStreams', fn (array $streams): bool => count($streams['latlng']) === 2
                && count($streams['power']) === 2)
            ->assertSee('Passo Mendola')
            ->assertSee('42,5 km')
            ->assertSee('220 W')
            ->assertSee('RPE 6')
            ->assertSee('Kontrolliert gefahren.')
            ->assertSee('Hammerhead')
            ->assertSee('1 Schaltvorgang')
            ->assertSee('id="ride-route"', false)
            ->assertSee('data-stream-chart="power"', false);
    }

    public function test_guest_and_another_user_cannot_see_an_activity(): void
    {
        $activity = Activity::factory()->create();

        $this->get(route('activities.show', $activity))->assertNotFound();

        $this->actingAs(User::factory()->create())
            ->get(route('activities.show', $activity))
            ->assertNotFound();
    }

    public function test_dashboard_links_to_the_activity_detail(): void
    {
        $user = User::factory()->create();
        $activity = Activity::factory()->for($user)->create(['name' => 'Linked ride']);

        $this->withoutVite()
            ->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('activities.show', $activity), false);
    }
}
