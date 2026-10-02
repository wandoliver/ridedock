<?php

namespace Tests\Feature;

use App\Domain\Training\Services\ActivityTrainingContextBuilder;
use App\Domain\Training\Services\ComparableEfficiency;
use App\Domain\Training\Services\TrainingZones;
use App\Models\Activity;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ComparableEfficiencyTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_time_weighted_paired_window_skips_warmup_and_reports_mean_ratio(): void
    {
        $user = $this->athlete();
        $ride = $this->ride($user, '2026-09-01', 200, 120);
        $stream = $ride->streams()->where('type', 'heart_rate')->first();
        $data = $stream->data;
        foreach ($data as &$sample) {
            if ($sample['elapsed_seconds'] >= 1200) {
                $sample['value'] = 150;
            }
        }
        $stream->update(['data' => $data]);
        $result = app(ComparableEfficiency::class)->assess($ride);
        $this->assertTrue($result['available']);
        $this->assertSame(600.0, $result['window_start_seconds']);
        $this->assertSame(1200.0, $result['duration_seconds']);
        $this->assertSame(135.0, $result['mean_heart_rate_bpm']);
        $this->assertSame(1.4815, $result['watts_per_bpm']);
        $this->assertSame(0.0, $result['power_cv_percent']);
    }

    public function test_gaps_short_windows_variable_power_and_missing_references_are_not_compared(): void
    {
        $user = $this->athlete();
        $service = app(ComparableEfficiency::class);
        $short = $this->ride($user, '2026-09-01', 200, 140, 1770);
        $this->assertSame('steady_window_missing', $service->assess($short)['reason']);
        $gap = $this->ride($user, '2026-09-02');
        $stream = $gap->streams()->where('type', 'power')->first();
        $stream->update(['data' => array_values(array_filter($stream->data, fn ($sample) => $sample['elapsed_seconds'] < 900 || $sample['elapsed_seconds'] > 960))]);
        $this->assertFalse($service->assess($gap)['available']);
        $variable = $this->ride($user, '2026-09-03');
        $stream = $variable->streams()->where('type', 'power')->first();
        $data = $stream->data;
        foreach ($data as $index => &$sample) {
            $sample['value'] = $index % 2 === 0 ? 165 : 225;
        }
        $stream->update(['data' => $data]);
        $this->assertFalse($service->assess($variable)['available']);
        $old = $this->ride($user, '2025-12-31');
        $this->assertSame('reference_missing', $service->assess($old)['reason']);
        $empty = Activity::factory()->for($user)->create(['started_at' => '2026-09-01']);
        $this->assertSame('samples_missing', $service->assess($empty)['reason']);
    }

    public function test_comparisons_require_matching_group_type_workload_and_ownership(): void
    {
        $user = $this->athlete();
        $previous = $this->ride($user, '2026-09-01', 200, 150);
        $this->ride($user, '2026-09-02', 220, 100)->update(['comparison_group' => 'Other conditions']);
        $this->ride($user, '2026-09-03', 200, 100)->update(['type' => 'virtual_ride']);
        $this->ride($user, '2026-09-04', 165, 100);
        $this->ride($user, '2026-09-05', 200, 100, 2700);
        $this->ride($this->athlete(), '2026-09-06', 200, 100);
        $current = $this->ride($user, '2026-09-10', 200, 125);
        $service = app(ComparableEfficiency::class);
        $report = $service->report($current);
        $this->assertSame($previous->localStartedAt()->toDateString(), $report['previous_date']);
        $this->assertSame(20.0, $report['change_percent']);
        $encoded = json_encode(app(ActivityTrainingContextBuilder::class)->build($current), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('PRIVATE_COMPARISON_GROUP', $encoded);
        $rows = $service->history($user, CarbonImmutable::parse('2026-10-02'));
        $this->assertCount(6, $rows);
        $this->actingAs($user)->withoutVite()->get(route('activities.show', $current))->assertOk()->assertSee(__('efficiency.title'));
        $this->get(route('dashboard'))->assertOk()->assertSee(__('efficiency.trends'));
        $this->patch(route('activities.comparison.update', $current), ['comparison_group' => 'New group', 'user_id' => 99999])->assertRedirect();
        $this->assertSame('New group', $current->fresh()->comparison_group);
        $this->patch(route('activities.comparison.update', $current), ['comparison_group' => str_repeat('x', 81)])->assertSessionHasErrors('comparison_group');
        $this->patch(route('activities.comparison.update', $current), ['comparison_group' => ''])->assertRedirect();
        $this->assertNull($current->fresh()->comparison_group);
        $this->actingAs(User::factory()->create())->patch(route('activities.comparison.update', $current), ['comparison_group' => 'Intrusion'])->assertNotFound();
        $this->assertNull($current->fresh()->comparison_group);
    }

    private function athlete(): User
    {
        $user = User::factory()->create();
        app(TrainingZones::class)->record($user, CarbonImmutable::parse('2026-01-01'), 300, 180);
        return $user;
    }

    private function ride(User $user, string $date, int $watts = 200, int $bpm = 140, int $duration = 1800): Activity
    {
        $ride = Activity::factory()->for($user)->create(['started_at' => $date, 'elapsed_time_s' => $duration, 'comparison_group' => 'PRIVATE_COMPARISON_GROUP']);
        foreach (['power' => $watts, 'heart_rate' => $bpm] as $type => $value) {
            $data = [];
            for ($time = 0; $time <= $duration; $time += 30) {
                $data[] = ['elapsed_seconds' => $time, 'value' => $value];
            }
            $ride->streams()->create(['type' => $type, 'series_type' => 'time', 'data' => $data]);
        }
        return $ride;
    }
}
