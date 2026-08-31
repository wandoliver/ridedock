<?php

namespace Tests\Unit\Domain\Training\Services;

use App\Domain\Training\Services\WeeklyTrainingContextBuilder;
use App\Models\Activity;
use App\Models\AthleteProfile;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class WeeklyTrainingContextBuilderTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_it_builds_private_schedule_aware_context_without_requiring_sensor_data(): void
    {
        CarbonImmutable::setTestNow('2026-08-30T12:00:00Z');
        $user = User::factory()->create(['name' => 'PRIVATE_WEEKLY_NAME']);
        AthleteProfile::factory()->for($user)->create([
            'goal' => 'Eine lange Tour entspannt fahren',
            'weekly_hours' => 6,
            'available_days' => ['tuesday', 'thursday', 'sunday'],
            'ftp_watts' => null,
            'max_heart_rate' => null,
        ]);

        foreach ([5, 12, 19] as $daysAgo) {
            Activity::factory()->for($user)->create([
                'started_at' => CarbonImmutable::parse('2026-08-31')->subDays($daysAgo),
                'moving_time_s' => 3600,
                'distance_m' => 30000,
                'average_heart_rate' => null,
                'average_cadence' => null,
                'average_power' => null,
                'weighted_average_power' => null,
                'rpe' => null,
                'notes' => 'PRIVATE_WEEKLY_NOTE',
                'summary_polyline' => 'PRIVATE_WEEKLY_ROUTE',
            ]);
        }

        $context = app(WeeklyTrainingContextBuilder::class)->build(
            $user,
            CarbonImmutable::parse('2026-08-31'),
        );

        $this->assertSame(2, $context['schema_version']);
        $this->assertSame('2026-08-31', $context['plan_start']);
        $this->assertSame('2026-09-06', $context['plan_end']);
        $this->assertCount(7, $context['plan_days']);
        $this->assertFalse($context['plan_days'][0]['available_for_training']);
        $this->assertTrue($context['plan_days'][1]['available_for_training']);
        $this->assertTrue($context['plan_days'][6]['available_for_training']);
        $this->assertSame(3, $context['recent_training']['six_week_summary']['activity_count']);
        $this->assertNull($context['athlete_profile']['optional_signals']['ftp_watts']);
        $this->assertNull($context['strategy']['goal_plan']);
        $this->assertNull($context['strategy']['training_block']);
        $this->assertSame([], $context['data_gaps']);

        $encodedContext = json_encode($context, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('PRIVATE_WEEKLY_NAME', $encodedContext);
        $this->assertStringNotContainsString('PRIVATE_WEEKLY_NOTE', $encodedContext);
        $this->assertStringNotContainsString('PRIVATE_WEEKLY_ROUTE', $encodedContext);
    }
}
