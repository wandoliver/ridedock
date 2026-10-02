<?php

namespace Tests\Unit\Domain\Training\Services;

use App\Domain\Training\Services\TrainingStatistics;
use App\Models\Activity;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class TrainingStatisticsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_summary_weights_effort_by_duration_and_marks_unknown_load(): void
    {
        $rides = collect([
            new Activity(['moving_time_s' => 1800, 'rpe' => 2]),
            new Activity(['moving_time_s' => 600, 'rpe' => 5]),
            new Activity(['moving_time_s' => 1200, 'rpe' => 8]),
            new Activity(['moving_time_s' => 3600, 'rpe' => null]),
            new Activity(['moving_time_s' => null, 'rpe' => 9]),
        ]);
        $summary = app(TrainingStatistics::class)->summary($rides);
        $this->assertSame(120.0, $summary['duration_minutes']);
        $this->assertSame(270.0, $summary['session_rpe_load']);
        $this->assertSame(50.0, $summary['rpe_coverage_percent']);
        $this->assertSame(3, $summary['rated_activity_count']);
        $this->assertSame(1, $summary['missing_duration_count']);
        $this->assertFalse($summary['load_complete']);
        $this->assertSame(['easy' => 30.0, 'moderate' => 10.0, 'hard' => 20.0, 'unrated' => 60.0], $summary['effort_minutes']);
    }

    public function test_no_recorded_rides_and_unrated_rides_are_distinct(): void
    {
        $statistics = app(TrainingStatistics::class);
        $empty = $statistics->summary(collect());
        $this->assertSame(0.0, $empty['session_rpe_load']);
        $this->assertTrue($empty['load_complete']);
        $unrated = $statistics->summary(collect([new Activity(['moving_time_s' => 3600, 'rpe' => null])]));
        $this->assertNull($unrated['session_rpe_load']);
        $this->assertFalse($unrated['load_complete']);
        $this->assertSame(0.0, $unrated['rpe_coverage_percent']);
        $invalid = $statistics->summary(collect([new Activity(['moving_time_s' => -10, 'rpe' => 15])]));
        $this->assertNull($invalid['session_rpe_load']);
        $this->assertSame(1, $invalid['missing_duration_count']);
        $this->assertSame(0.0, $invalid['duration_minutes']);
    }

    public function test_report_compares_only_completed_weeks_and_is_owner_scoped(): void
    {
        $user = User::factory()->create();
        $this->seedBaseline($user);
        Activity::factory()->for($user)->create(['started_at' => '2026-09-23T10:00:00Z', 'moving_time_s' => 7200, 'rpe' => 4]);
        Activity::factory()->for($user)->create(['started_at' => '2026-09-30T10:00:00Z', 'moving_time_s' => 18000, 'rpe' => 9]);
        Activity::factory()->for($user)->create(['started_at' => '2026-10-03T10:00:00Z', 'moving_time_s' => 99999, 'rpe' => 10]);
        Activity::factory()->create(['started_at' => '2026-09-23T10:00:00Z', 'moving_time_s' => 99999, 'rpe' => 10]);
        $report = app(TrainingStatistics::class)->report($user, CarbonImmutable::parse('2026-10-02T12:00:00Z')->locale('en_US'));
        $this->assertCount(6, $report['weeks']);
        $this->assertSame(6, $report['summary']['activity_count']);
        $this->assertSame('2026-08-24', $report['window_start']);
        $this->assertSame(5.0, $report['weeks'][5]['duration_hours']);
        $this->assertTrue($report['weeks'][5]['partial']);
        $this->assertFalse($report['weeks'][4]['partial']);
        $this->assertSame(100.0, $report['comparison']['volume_change_percent']);
        $this->assertSame(100.0, $report['comparison']['load_change_percent']);
    }

    public function test_missing_rpe_suppresses_load_comparison_but_preserves_volume_comparison(): void
    {
        $user = User::factory()->create();
        $this->seedBaseline($user);
        Activity::factory()->for($user)->create(['started_at' => '2026-09-23T10:00:00Z', 'moving_time_s' => 7200, 'rpe' => null]);
        $report = app(TrainingStatistics::class)->report($user, CarbonImmutable::parse('2026-10-02T12:00:00Z')->locale('en_US'));
        $this->assertNull($report['comparison']['load_change_percent']);
        $this->assertSame(100.0, $report['comparison']['volume_change_percent']);
        $this->assertSame(0.0, $report['weeks'][5]['session_rpe_load']);
        $this->assertNull($report['weeks'][4]['session_rpe_load']);
    }

    public function test_missing_duration_suppresses_comparisons_and_short_history_is_not_extrapolated(): void
    {
        $user = User::factory()->create();
        $ride = Activity::factory()->for($user)->create(['started_at' => '2026-09-23T10:00:00Z', 'moving_time_s' => 7200, 'rpe' => 5]);
        $statistics = app(TrainingStatistics::class);
        $asOf = CarbonImmutable::parse('2026-10-02T12:00:00Z');
        $report = $statistics->report($user, $asOf);
        $this->assertFalse($report['comparison']['has_history']);
        $this->assertNull($report['comparison']['volume_change_percent']);
        $this->seedBaseline($user);
        $ride->update(['moving_time_s' => null]);
        $report = $statistics->report($user, $asOf);
        $this->assertTrue($report['comparison']['has_history']);
        $this->assertNull($report['comparison']['volume_change_percent']);
        $this->assertNull($report['comparison']['load_change_percent']);
    }

    public function test_rest_weeks_remain_in_the_baseline_and_zero_baselines_have_no_percentage(): void
    {
        $user = User::factory()->create();
        $this->seedBaseline($user);
        $user->activities()->whereDate('started_at', '2026-09-07')->delete();
        Activity::factory()->for($user)->create(['started_at' => '2026-09-23T10:00:00Z', 'moving_time_s' => 3600, 'rpe' => 4]);
        $statistics = app(TrainingStatistics::class);
        $asOf = CarbonImmutable::parse('2026-10-02T12:00:00Z');
        $report = $statistics->report($user, $asOf);
        $this->assertSame(0, $report['weeks'][2]['activity_count']);
        $this->assertSame(33.3, $report['comparison']['volume_change_percent']);
        $user->activities()->whereBetween('started_at', ['2026-08-24', '2026-09-21'])->delete();
        $report = $statistics->report($user, $asOf);
        $this->assertNull($report['comparison']['volume_change_percent']);
        $this->assertNull($report['comparison']['load_change_percent']);
    }

    public function test_week_boundaries_use_recording_timezone_with_invalid_timezone_fallback(): void
    {
        config(['app.timezone' => 'UTC']);
        $user = User::factory()->create();
        Activity::factory()->for($user)->create(['started_at' => '2026-08-23T23:30:00Z', 'timezone' => 'Europe/Rome', 'moving_time_s' => 3600]);
        Activity::factory()->for($user)->create(['started_at' => '2026-08-23T23:30:00Z', 'timezone' => 'UTC', 'moving_time_s' => 3600]);
        Activity::factory()->for($user)->create(['started_at' => '2026-09-27T23:30:00Z', 'timezone' => 'Europe/Rome', 'moving_time_s' => 3600]);
        Activity::factory()->for($user)->create(['started_at' => '2026-09-27T23:30:00Z', 'timezone' => 'invalid/timezone', 'moving_time_s' => 3600]);
        $report = app(TrainingStatistics::class)->report($user, CarbonImmutable::parse('2026-10-02T12:00:00Z')->locale('en_US'));
        $this->assertSame(3, $report['summary']['activity_count']);
        $this->assertSame(1, $report['weeks'][0]['activity_count']);
        $this->assertSame(1, $report['weeks'][4]['activity_count']);
        $this->assertSame(1, $report['weeks'][5]['activity_count']);
    }

    private function seedBaseline(User $user): void
    {
        foreach (['2026-08-10', '2026-08-24', '2026-08-31', '2026-09-07', '2026-09-14'] as $date) {
            Activity::factory()->for($user)->create(['started_at' => $date.'T10:00:00Z', 'moving_time_s' => 3600, 'rpe' => 4]);
        }
    }
}
