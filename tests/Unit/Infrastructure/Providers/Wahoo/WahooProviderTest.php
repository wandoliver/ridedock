<?php

namespace Tests\Unit\Infrastructure\Providers\Wahoo;

use App\Infrastructure\Providers\Wahoo\WahooProvider;
use App\Models\ProviderConnection;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WahooProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('activity-providers.wahoo', [
            ...config('activity-providers.wahoo'),
            'enabled' => true,
            'client_id' => 'wahoo-client',
            'client_secret' => 'wahoo-secret',
            'redirect_uri' => 'http://127.0.0.1:8080/api/v1/oauth/wahoo/callback',
            'api_url' => 'https://api.wahooligan.com/v1',
            'auth_url' => 'https://api.wahooligan.com',
        ]);
    }

    public function test_maps_wahoo_summary_units_and_cycling_type(): void
    {
        $activity = (new WahooProvider)->mapActivity($this->workout([
            'workout_type_id' => 13,
            'workout_summary' => [
                'name' => 'Trail ride',
                'distance_accum' => '24909.71',
                'duration_active_accum' => '3600.4',
                'duration_total_accum' => '3725.6',
                'ascent_accum' => '450.00',
                'cadence_avg' => '82.50',
                'heart_rate_avg' => '144.6',
                'power_avg' => '194.59',
                'power_bike_np_last' => '225.00',
                'speed_avg' => '6.92',
                'work_accum' => '700524.00',
                'time_zone' => 'Europe/Rome',
                'updated_at' => '2026-08-30T08:05:00Z',
            ],
        ]));

        $this->assertSame('mountain_bike', $activity->type);
        $this->assertSame(24909.71, $activity->distanceM);
        $this->assertSame(3600, $activity->movingTimeS);
        $this->assertSame(3726, $activity->elapsedTimeS);
        $this->assertSame(450.0, $activity->elevationGainM);
        $this->assertSame(145, $activity->averageHeartRate);
        $this->assertSame(82.5, $activity->averageCadence);
        $this->assertSame(225.0, $activity->weightedAveragePower);
        $this->assertSame(700.524, $activity->energyKj);
        $this->assertSame('Europe/Rome', $activity->timezone);
    }

    public function test_lists_only_completed_cycling_workouts_and_paginates(): void
    {
        Http::fake([
            'api.wahooligan.com/v1/workouts*' => Http::response([
                'workouts' => [
                    $this->workout(),
                    $this->workout(['id' => 2, 'workout_summary' => null]),
                    $this->workout(['id' => 3, 'workout_type_id' => 1]),
                ],
                'total' => 75,
                'page' => 1,
                'per_page' => 50,
            ]),
        ]);

        $page = (new WahooProvider)->activities($this->connection());

        $this->assertCount(1, $page->activities);
        $this->assertSame('1', $page->activities[0]->externalId);
        $this->assertSame('2', $page->nextCursor);
        Http::assertSent(fn ($request): bool => $request->url() === 'https://api.wahooligan.com/v1/workouts?page=1&per_page=50');
    }

    public function test_stops_pagination_after_reaching_the_incremental_sync_boundary(): void
    {
        Http::fake([
            'api.wahooligan.com/v1/workouts*' => Http::response([
                'workouts' => [
                    $this->workout(['starts' => '2026-08-30T06:00:00Z']),
                    $this->workout(['id' => 2, 'starts' => '2026-08-20T06:00:00Z']),
                ],
                'total' => 100,
                'page' => 1,
                'per_page' => 50,
            ]),
        ]);

        $page = (new WahooProvider)->activities(
            $this->connection(),
            CarbonImmutable::parse('2026-08-25T00:00:00Z'),
        );

        $this->assertCount(1, $page->activities);
        $this->assertNull($page->nextCursor);
    }

    public function test_downloads_the_fit_file_from_the_workout_summary(): void
    {
        Http::fake([
            'api.wahooligan.com/v1/workouts/1/workout_summary' => Http::response([
                'file' => ['url' => 'https://cdn.wahooligan.com/workout.fit'],
            ]),
            'cdn.wahooligan.com/workout.fit' => Http::response('FIT contents'),
        ]);

        $contents = (new WahooProvider)->downloadActivityFile($this->connection(), '1');

        $this->assertSame('FIT contents', $contents);
        Http::assertSentCount(2);
    }

    public function test_refreshes_a_confidential_client_token(): void
    {
        Http::fake([
            'api.wahooligan.com/oauth/token' => Http::response([
                'access_token' => 'new-access-token',
                'refresh_token' => 'new-refresh-token',
                'expires_in' => 7200,
            ]),
        ]);
        $connection = $this->connection();
        $connection->refresh_token = 'old-refresh-token';
        $connection->external_user_id = '60462';

        $token = (new WahooProvider)->refreshAccessToken($connection);

        $this->assertSame('new-access-token', $token->accessToken);
        $this->assertSame('new-refresh-token', $token->refreshToken);
        $this->assertSame('60462', $token->externalUserId);
        $this->assertSame(['user_read', 'workouts_read', 'offline_data'], $token->scopes);
        Http::assertSent(fn ($request): bool => $request->isForm()
            && $request['client_id'] === 'wahoo-client'
            && $request['client_secret'] === 'wahoo-secret'
            && $request['grant_type'] === 'refresh_token'
            && $request['refresh_token'] === 'old-refresh-token');
    }

    private function connection(): ProviderConnection
    {
        return new ProviderConnection(['access_token' => 'access-token']);
    }

    /** @param array<string, mixed> $overrides */
    private function workout(array $overrides = []): array
    {
        return [
            'id' => 1,
            'starts' => '2026-08-30T06:00:00Z',
            'name' => 'Morning ride',
            'workout_type_id' => 15,
            'updated_at' => '2026-08-30T08:00:00Z',
            'workout_summary' => [
                'name' => 'Morning ride',
                'distance_accum' => '50000.00',
                'duration_active_accum' => '7200.00',
                'duration_total_accum' => '7500.00',
                'updated_at' => '2026-08-30T08:00:00Z',
                'file' => ['url' => 'https://cdn.wahooligan.com/workout.fit'],
            ],
            ...$overrides,
        ];
    }
}
