<?php

namespace Tests\Feature;

use App\Domain\Training\Services\SensorZoneDistribution;
use App\Domain\Training\Services\TrainingZones;
use App\Models\Activity;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class SensorZoneDistributionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_real_time_weighting_keeps_coasting_and_exposes_gaps_and_invalid_samples(): void
    {
        $user = User::factory()->create();
        app(TrainingZones::class)->record($user, CarbonImmutable::parse('2026-01-01'), 200, 180);
        $ride = Activity::factory()->for($user)->create(['started_at' => '2026-09-01', 'elapsed_time_s' => 100]);
        $ride->streams()->create(['type' => 'power', 'series_type' => 'time', 'data' => [
            ['elapsed_seconds' => 20, 'value' => 150], ['elapsed_seconds' => 0, 'value' => 0],
            ['elapsed_seconds' => 10, 'value' => 140], ['elapsed_seconds' => 70, 'value' => null],
            ['elapsed_seconds' => 80, 'value' => 300], ['elapsed_seconds' => 110, 'value' => 400],
        ]]);
        $ride->streams()->create(['type' => 'heart_rate', 'series_type' => 'time', 'data' => [
            ['elapsed_seconds' => 0, 'value' => 180], ['elapsed_seconds' => 10, 'value' => 181],
            ['elapsed_seconds' => 20, 'value' => 0], ['elapsed_seconds' => 30, 'value' => 100],
        ]]);
        $result = app(SensorZoneDistribution::class)->activity($ride);
        $this->assertSame(10.0, $result['power']['seconds']['P1']);
        $this->assertSame(10.0, $result['power']['seconds']['P2']);
        $this->assertSame(20.0, $result['power']['seconds']['P6']);
        $this->assertSame(40.0, $result['power']['covered_seconds']);
        $this->assertSame(60.0, $result['power']['unknown_seconds']);
        $this->assertSame(10.0, $result['heart_rate']['seconds']['H5']);
        $this->assertSame(10.0, $result['heart_rate']['seconds']['HR>100']);
        $this->assertSame(80.0, $result['heart_rate']['unknown_seconds']);
    }

    public function test_scalar_series_require_explicit_cadence_and_old_references_are_not_guessed(): void
    {
        $user = User::factory()->create();
        app(TrainingZones::class)->record($user, CarbonImmutable::parse('2026-09-02'), 200, 180);
        $ride = Activity::factory()->for($user)->create(['started_at' => '2026-09-01', 'elapsed_time_s' => 100]);
        $ride->streams()->create(['type' => 'power', 'series_type' => 'time', 'data' => [100, 200, 300], 'metadata' => ['interval_seconds' => 10]]);
        $service = app(SensorZoneDistribution::class);
        $this->assertSame('reference_missing', $service->activity($ride)['power']['reason']);
        app(TrainingZones::class)->record($user, CarbonImmutable::parse('2026-01-01'), 200, 180);
        $this->assertSame(30.0, $service->activity($ride)['power']['covered_seconds']);
        $ride->streams()->first()->update(['metadata' => null]);
        $ride->unsetRelation('streams');
        $this->assertSame('samples_missing', $service->activity($ride)['power']['reason']);
    }

    public function test_history_uses_each_rides_snapshot_and_includes_unknown_time_without_other_users(): void
    {
        $user = User::factory()->create();
        app(TrainingZones::class)->record($user, CarbonImmutable::parse('2026-09-01'), 200, 180);
        app(TrainingZones::class)->record($user, CarbonImmutable::parse('2026-09-15'), 400, 180);
        foreach (['2026-08-31', '2026-09-01', '2026-09-15'] as $date) {
            $ride = Activity::factory()->for($user)->create(['started_at' => $date, 'elapsed_time_s' => 60]);
            $ride->streams()->create(['type' => 'power', 'series_type' => 'time', 'data' => [200, 200], 'metadata' => ['interval_seconds' => 30]]);
        }
        Activity::factory()->create(['started_at' => '2026-09-20', 'elapsed_time_s' => 9999]);
        $result = app(SensorZoneDistribution::class)->history($user, CarbonImmutable::parse('2026-10-02'));
        $this->assertSame(180.0, $result['power']['duration_seconds']);
        $this->assertSame(60.0, $result['power']['unknown_seconds']);
        $this->assertSame(60.0, $result['power']['seconds']['P4']);
        $this->assertSame(60.0, $result['power']['seconds']['P1']);
        $this->actingAs($user)->withoutVite()->get(route('activities.show', $ride))->assertOk()->assertSee(__('zones.sensor_title'));
        $this->get(route('analytics'))->assertOk()->assertSee(__('zones.sensor_title'));
    }
}
