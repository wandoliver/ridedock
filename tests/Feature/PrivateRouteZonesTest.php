<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ActivitySource;
use App\Models\ActivityStream;
use App\Models\ProviderConnection;
use App\Models\User;
use App\Modules\Heatmap\Services\RoutePrivacy;
use App\Modules\Heatmap\Services\StreamRouteGeometryProvider;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PrivateRouteZonesTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_zone_splits_routes_on_all_output_paths_without_changing_originals(): void
    {
        config()->set('heatmap.privacy_trim_m', 0);
        config()->set('heatmap.sample_every', 20);
        $user = User::factory()->create();
        $user->privacyZones()->create(['name' => 'Synthetic private place', 'latitude' => 0, 'longitude' => 0, 'radius_m' => 400]);
        $points = [[-0.02, 0.0], [-0.01, 0.0], [0.0, 0.0], [0.01, 0.0], [0.02, 0.0]];
        $privacy = app(RoutePrivacy::class);
        $activity = Activity::factory()->for($user)->create(['summary_polyline' => $privacy->encode($points)]);
        $connection = ProviderConnection::factory()->for($user)->create();
        ActivitySource::factory()->for($activity)->for($connection, 'connection')->create();
        $stream = ActivityStream::factory()->for($activity)->create(['type' => 'latlng', 'data' => $points]);
        $safe = [[[-0.02, 0.0], [-0.01, 0.0]], [[0.01, 0.0], [0.02, 0.0]]];
        $this->assertSame($safe, $privacy->activitySegments($activity));
        $this->actingAs($user)->get(route('activities.show', $activity))->assertOk()
            ->assertViewHas('activityStreams', fn ($streams) => $streams['latlng'] === [
                ['value' => [-0.02, 0.0]], ['value' => [-0.01, 0.0]], null,
                ['value' => [0.01, 0.0]], ['value' => [0.02, 0.0]],
            ]);
        $routes = iterator_to_array(app(StreamRouteGeometryProvider::class)->routes($user));
        $this->assertCount(2, $routes);
        $this->assertSame($safe, array_map(fn ($route) => $privacy->decode($route['polyline']), $routes));
        $this->get(route('analytics'))->assertOk()->assertViewHas('heatmapRouteCount', 1);
        Sanctum::actingAs($user, ['activities:read']);
        $this->getJson("/api/v1/activities/{$activity->id}")->assertOk()->assertJsonMissingPath('data.route_segments');
        $response = $this->getJson("/api/v1/activities/{$activity->id}?include_route=1")->assertOk()->assertJsonPath('data.summary_polyline', null);
        $this->assertSame($safe, array_map($privacy->decode(...), $response->json('data.route_segments')));
        $export = $this->getJson('/api/v1/analysis/export?include_route=1')->assertOk()->assertJsonPath('activities.0.summary_polyline', null);
        $this->assertSame($safe, array_map($privacy->decode(...), $export->json('activities.0.route_segments')));
        $this->assertEquals($points, $stream->refresh()->data);
        $this->assertSame($privacy->encode($points), $activity->refresh()->summary_polyline);
    }

    public function test_crossing_edge_is_removed_even_when_both_recorded_points_are_outside(): void
    {
        $user = User::factory()->create();
        $user->privacyZones()->create(['name' => 'Synthetic zone', 'latitude' => 0, 'longitude' => 0, 'radius_m' => 400]);
        $points = [[-0.03, 0.0], [-0.02, 0.0], [0.02, 0.0], [0.03, 0.0]];
        $segments = app(RoutePrivacy::class)->segments($user, $points);
        $this->assertSame([array_slice($points, 0, 2), array_slice($points, 2, 2)], $segments);
        $this->assertSame([], app(RoutePrivacy::class)->segments($user, [[0, 0], [0.001, 0.001]]));
    }

    public function test_dateline_crossing_and_invalid_gaps_are_not_bridged(): void
    {
        $user = User::factory()->create();
        $user->privacyZones()->create(['name' => 'Synthetic dateline zone', 'latitude' => 0, 'longitude' => 180, 'radius_m' => 400]);
        $points = [[0.0, 179.98], [0.0, 179.99], [0.0, -179.99], [0.0, -179.98]];
        $privacy = app(RoutePrivacy::class);
        $this->assertSame([array_slice($points, 0, 2), array_slice($points, 2, 2)], $privacy->segments($user, $points));
        $this->assertCount(2, $privacy->segments($user, [[0.01, 0.01], [0.02, 0.02], null, [0.03, 0.03], [0.04, 0.04]]));
    }

    public function test_summary_only_routes_are_masked_and_zones_are_owner_scoped(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $other->privacyZones()->create(['name' => 'Another users zone', 'latitude' => 0, 'longitude' => 0, 'radius_m' => 400]);
        $privacy = app(RoutePrivacy::class);
        $points = [[-0.02, 0.0], [-0.01, 0.0], [0.0, 0.0], [0.01, 0.0], [0.02, 0.0]];
        $activity = Activity::factory()->for($user)->create(['summary_polyline' => $privacy->encode($points)]);
        $this->assertCount(1, $privacy->activitySegments($activity));
        $user->privacyZones()->create(['name' => 'Own zone', 'latitude' => 0, 'longitude' => 0, 'radius_m' => 400]);
        $activity->unsetRelation('user');
        $this->assertCount(2, $privacy->activitySegments($activity));
        $this->assertSame([], $privacy->decode('invalid truncated ~~'));
    }

    public function test_high_latitude_map_shortcuts_are_masked_too(): void
    {
        $user = User::factory()->create();
        $user->privacyZones()->create(['name' => 'Synthetic northern zone', 'latitude' => 80, 'longitude' => 0, 'radius_m' => 400]);
        $points = [[80.0, -20.0], [80.0, -10.0], [80.0, 10.0], [80.0, 20.0]];
        $this->assertSame([array_slice($points, 0, 2), array_slice($points, 2, 2)], app(RoutePrivacy::class)->segments($user, $points));
    }

    public function test_routes_shorter_than_privacy_trim_do_not_expose_endpoints(): void
    {
        $user = User::factory()->create();
        $this->assertSame([], app(RoutePrivacy::class)->segments($user, [[0, 0], [0.001, 0.001]], 400));
    }
}
