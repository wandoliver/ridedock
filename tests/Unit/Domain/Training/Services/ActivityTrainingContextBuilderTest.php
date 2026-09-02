<?php

namespace Tests\Unit\Domain\Training\Services;

use App\Domain\Training\Services\ActivityTrainingContextBuilder;
use App\Models\Activity;
use App\Models\AthleteProfile;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ActivityTrainingContextBuilderTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_it_builds_versioned_metrics_without_private_source_data(): void
    {
        CarbonImmutable::setTestNow('2026-08-30T12:00:00Z');
        $user = User::factory()->create(['name' => 'PRIVATE_NAME', 'email' => 'private@example.com']);
        AthleteProfile::factory()->for($user)->create([
            'ftp_watts' => 250,
            'max_heart_rate' => 190,
            'weekly_hours' => 8,
        ]);
        $activity = Activity::factory()->for($user)->create([
            'name' => 'Tempo Ride',
            'started_at' => '2026-08-30T08:00:00Z',
            'moving_time_s' => 5400,
            'distance_m' => 45000,
            'average_speed_mps' => 8.333,
            'weighted_average_power' => 225,
            'average_heart_rate' => 152,
            'rpe' => 6,
            'notes' => 'PRIVATE_ACTIVITY_NOTE',
            'summary_polyline' => 'PRIVATE_ROUTE',
            'metadata' => ['provider_secret' => 'PRIVATE_PROVIDER_VALUE'],
        ]);

        foreach ([7, 14, 21] as $daysAgo) {
            Activity::factory()->for($user)->create([
                'started_at' => CarbonImmutable::parse($activity->started_at)->subDays($daysAgo),
                'moving_time_s' => 3600,
                'distance_m' => 30000,
                'elevation_gain_m' => 300,
                'rpe' => 5,
            ]);
        }

        $context = app(ActivityTrainingContextBuilder::class)->build($activity);

        $this->assertSame(3, $context['schema_version']);
        $this->assertSame(90.0, $context['activity']['duration_minutes']);
        $this->assertSame(30.0, $context['activity']['average_speed_kmh']);
        $this->assertSame(0.9, $context['activity']['weighted_power_to_ftp_ratio']);
        $this->assertSame(540.0, $context['activity']['session_rpe_load']);
        $this->assertSame(3, $context['recent_training']['summary']['activity_count']);
        $this->assertCount(6, $context['recent_training']['weeks']);
        $this->assertSame([], $context['data_gaps']);
        $this->assertFalse($context['privacy']['gps_coordinates_included']);

        $encoded = json_encode($context, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('PRIVATE_NAME', $encoded);
        $this->assertStringNotContainsString('private@example.com', $encoded);
        $this->assertStringNotContainsString('PRIVATE_ACTIVITY_NOTE', $encoded);
        $this->assertStringNotContainsString('PRIVATE_ROUTE', $encoded);
        $this->assertStringNotContainsString('PRIVATE_PROVIDER_VALUE', $encoded);
    }

    public function test_optional_profile_and_sensor_values_are_not_reported_as_data_gaps(): void
    {
        $user = User::factory()->create();
        AthleteProfile::factory()->for($user)->create([
            'goal' => 'Regelmäßig und gesund Rad fahren',
            'ftp_watts' => null,
            'max_heart_rate' => null,
        ]);
        $activity = Activity::factory()->for($user)->create([
            'started_at' => '2026-08-30T08:00:00Z',
            'average_heart_rate' => null,
            'max_heart_rate' => null,
            'average_speed_mps' => null,
            'average_cadence' => null,
            'average_power' => null,
            'weighted_average_power' => null,
            'max_power' => null,
            'rpe' => null,
        ]);

        foreach ([7, 14, 21] as $daysAgo) {
            Activity::factory()->for($user)->create([
                'started_at' => $activity->started_at->subDays($daysAgo),
            ]);
        }

        $context = app(ActivityTrainingContextBuilder::class)->build($activity);

        $this->assertNull($context['athlete_profile']['ftp_watts']);
        $this->assertNull($context['activity']['average_cadence_rpm']);
        $this->assertNull($context['activity']['average_speed_kmh']);
        $this->assertNull($context['activity']['average_power_watts']);
        $this->assertSame([], $context['data_gaps']);
    }
}
