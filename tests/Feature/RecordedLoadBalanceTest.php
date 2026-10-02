<?php

namespace Tests\Feature;

use App\Domain\Training\Services\ActivityTrainingContextBuilder;
use App\Domain\Training\Services\RecordedLoadBalance;
use App\Domain\Training\Services\WeeklyTrainingContextBuilder;
use App\Models\Activity;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class RecordedLoadBalanceTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_daily_sums_include_empty_days_but_exclude_today_and_other_users(): void
    {
        CarbonImmutable::setTestNow('2026-10-02T12:00:00Z');
        $user = User::factory()->create();
        $this->ride($user, '2026-08-01');
        $this->ride($user, '2026-09-25');
        $this->ride($user, '2026-09-25');
        $this->ride($user, '2026-09-26');
        $this->ride($user, '2026-10-02', null);
        $this->ride(User::factory()->create(), '2026-09-27', null);
        $report = app(RecordedLoadBalance::class)->report($user, CarbonImmutable::now());
        $this->assertSame('2026-10-01', $report['window_end']);
        $this->assertSame('2026-08-21', $report['forty_two_days']['start']);
        $this->assertSame(3, $report['seven_days']['ride_count']);
        $this->assertSame(200.0, $report['daily_loads'][0]['load']);
        $this->assertSame(100.0, $report['daily_loads'][1]['load']);
        $this->assertSame(0.0, $report['daily_loads'][6]['load']);
        $mean = 300 / 7;
        $deviation = sqrt(((200 - $mean) ** 2 + (100 - $mean) ** 2 + 5 * $mean ** 2) / 7);
        $this->assertSame(round($mean / $deviation, 2), $report['monotony']['value']);
        $this->assertSame(round(300 * $mean / $deviation, 1), $report['monotony']['strain']);
        $this->assertSame(-35.7, $report['load_balance']['value']);
        $this->assertFalse($report['freshness']['available']);
        $this->actingAs($user)->withoutVite()->get(route('dashboard'))->assertOk()->assertSee(__('balance.title'));
    }

    public function test_missing_ratings_or_duration_make_their_period_unknown(): void
    {
        CarbonImmutable::setTestNow('2026-10-02T12:00:00Z');
        $user = User::factory()->create();
        $this->ride($user, '2026-08-01');
        $this->ride($user, '2026-09-01', null);
        $this->ride($user, '2026-09-25');
        $service = app(RecordedLoadBalance::class);
        $report = $service->report($user, CarbonImmutable::now());
        $this->assertNotNull($report['monotony']['value']);
        $this->assertNull($report['load_balance']['value']);
        $this->assertSame('incomplete_load', $report['load_balance']['reason']);
        $this->ride($user, '2026-09-25')->update(['moving_time_s' => null]);
        $report = $service->report($user, CarbonImmutable::now());
        $this->assertSame('incomplete_load', $report['monotony']['reason']);
        $this->assertNull($report['monotony']['strain']);
        $this->assertNull($report['daily_loads'][0]['load']);
        $this->assertSame(1, $report['seven_days']['missing_load_ride_count']);
        $this->assertSame(2, $report['forty_two_days']['missing_load_ride_count']);
    }

    public function test_empty_short_zero_and_constant_histories_have_explicit_unavailable_states(): void
    {
        CarbonImmutable::setTestNow('2026-10-02T12:00:00Z');
        $user = User::factory()->create();
        $service = app(RecordedLoadBalance::class);
        $this->assertSame('insufficient_history', $service->report($user, CarbonImmutable::now())['monotony']['reason']);
        $this->ride($user, '2026-09-30');
        $report = $service->report($user, CarbonImmutable::now());
        $this->assertSame('insufficient_history', $report['monotony']['reason']);
        $this->assertNull($report['daily_loads'][0]['load']);
        $user->activities()->delete();
        $this->ride($user, '2026-08-01');
        $report = $service->report($user, CarbonImmutable::now());
        $this->assertSame('no_load', $report['monotony']['reason']);
        $this->assertSame('no_load', $report['load_balance']['reason']);
        for ($day = 25; $day <= 30; $day++) {
            $this->ride($user, '2026-09-'.$day);
        }
        $this->ride($user, '2026-10-01');
        $report = $service->report($user, CarbonImmutable::now());
        $this->assertNull($report['monotony']['value']);
        $this->assertSame('no_variation', $report['monotony']['reason']);
        $this->assertSame(-83.3, $report['load_balance']['value']);
    }

    public function test_calendar_dates_and_historical_ai_context_do_not_leak_subsequent_load(): void
    {
        CarbonImmutable::setTestNow('2026-10-02T12:00:00Z');
        $user = User::factory()->create();
        $this->ride($user, '2026-08-01');
        $this->ride($user, '2026-09-30T23:30:00Z')->update(['timezone' => 'Europe/Rome']);
        $this->ride($user, '2026-09-30T23:30:00Z')->update(['timezone' => 'invalid/timezone']);
        $report = app(RecordedLoadBalance::class)->report($user, CarbonImmutable::now());
        $this->assertSame(100.0, $report['daily_loads'][5]['load']);
        $this->assertSame(100.0, $report['daily_loads'][6]['load']);
        $historical = $this->ride($user, '2026-09-26T23:30:00Z')->fresh();
        $context = app(ActivityTrainingContextBuilder::class)->build($historical);
        $this->assertSame('2026-09-26', $context['recent_training']['recorded_load_balance']['window_end']);
        $before = $context['recent_training']['recorded_load_balance'];
        $this->ride($user, '2026-09-28', null);
        $after = app(ActivityTrainingContextBuilder::class)->build($historical)['recent_training']['recorded_load_balance'];
        $this->assertSame($before, $after);
        $weekly = app(WeeklyTrainingContextBuilder::class)->build($user);
        $this->assertSame('incomplete_load', $weekly['recent_training']['recorded_load_balance']['monotony']['reason']);
    }

    private function ride(User $user, string $date, ?int $rpe = 2): Activity
    {
        return Activity::factory()->for($user)->create(['started_at' => $date, 'moving_time_s' => 3000, 'rpe' => $rpe]);
    }
}
