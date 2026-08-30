<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ActivityStream;
use App\Models\User;
use App\Modules\Heatmap\Services\StreamRouteGeometryProvider;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class StreamRouteGeometryProviderTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_returns_only_the_users_routes_as_encoded_polylines(): void
    {
        config()->set('heatmap.privacy_trim_m', 0);
        config()->set('heatmap.sample_every', 1);
        $user = User::factory()->create();
        $activity = Activity::factory()->for($user)->create([
            'started_at' => '2026-08-30T07:00:00Z',
        ]);
        ActivityStream::factory()->for($activity)->create([
            'type' => 'latlng',
            'data' => [
                ['value' => [38.5, -120.2]],
                ['value' => [40.7, -120.95]],
                ['value' => [43.252, -126.453]],
            ],
        ]);
        $otherActivity = Activity::factory()->create();
        ActivityStream::factory()->for($otherActivity)->create([
            'type' => 'latlng',
            'data' => [['value' => [50.0, 8.0]], ['value' => [50.1, 8.1]]],
        ]);

        $routes = iterator_to_array((new StreamRouteGeometryProvider)->routes($user));

        $this->assertCount(1, $routes);
        $this->assertSame($activity->id, $routes[0]['activity_id']);
        $this->assertSame(3, $routes[0]['point_count']);
        $this->assertSame('_p~iF~ps|U_ulLnnqC_mqNvxq`@', $routes[0]['polyline']);
    }

    public function test_it_trims_route_ends_for_privacy_and_honours_date_filters(): void
    {
        config()->set('heatmap.privacy_trim_m', 200);
        config()->set('heatmap.sample_every', 1);
        $user = User::factory()->create();
        $included = Activity::factory()->for($user)->create([
            'started_at' => '2026-08-15T07:00:00Z',
        ]);
        ActivityStream::factory()->for($included)->create([
            'type' => 'latlng',
            'data' => collect(range(0, 6))->map(fn (int $index): array => [
                'value' => [46.0 + ($index * 0.001), 11.0],
            ])->all(),
        ]);
        $excluded = Activity::factory()->for($user)->create([
            'started_at' => '2026-07-01T07:00:00Z',
        ]);
        ActivityStream::factory()->for($excluded)->create([
            'type' => 'latlng',
            'data' => [['value' => [46.0, 11.0]], ['value' => [46.1, 11.1]]],
        ]);

        $routes = iterator_to_array((new StreamRouteGeometryProvider)->routes(
            $user,
            CarbonImmutable::parse('2026-08-01T00:00:00Z'),
            CarbonImmutable::parse('2026-08-31T23:59:59Z'),
        ));

        $this->assertCount(1, $routes);
        $this->assertSame($included->id, $routes[0]['activity_id']);
        $this->assertSame(3, $routes[0]['point_count']);
    }
}
