<?php

namespace Tests\Feature;

use App\Domain\Training\Enums\AiAnalysisStatus;
use App\Domain\Training\Services\PlanActivityMatcher;
use App\Models\Activity;
use App\Models\AiAnalysis;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PlanDayControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function plan(User $user): AiAnalysis
    {
        return AiAnalysis::factory()->for($user)->create([
            'activity_id' => null, 'type' => 'weekly_plan', 'status' => AiAnalysisStatus::Completed,
            'output' => ['total_duration_minutes' => 60, 'days' => [
                ['date' => '2026-09-05', 'type' => 'ride', 'title' => 'Intervals', 'duration_minutes' => 60],
                ['date' => '2026-09-06', 'type' => 'rest', 'title' => 'Rest', 'duration_minutes' => null],
            ]],
        ]);
    }

    public function test_completion_can_be_toggled_without_changing_duration(): void
    {
        $user = User::factory()->create();
        $plan = $this->plan($user);
        $url = route('coach.days.update', [$plan->id, '2026-09-05']);
        $this->actingAs($user)->patch($url, ['action' => 'complete'])
            ->assertRedirect(route('coach.index', ['plan' => $plan->id, 'day' => '2026-09-05']).'#week');
        $this->assertTrue($plan->fresh()->output['days'][0]['completed']);
        $this->patch($url, ['action' => 'complete'])->assertRedirect();
        $this->assertFalse($plan->fresh()->output['days'][0]['completed']);
        $this->assertSame(60, $plan->fresh()->output['total_duration_minutes']);
    }

    public function test_move_swaps_days_and_preserves_completion_and_total(): void
    {
        $user = User::factory()->create();
        $plan = $this->plan($user);
        $this->actingAs($user)->patch(route('coach.days.update', [$plan->id, '2026-09-05']), [
            'action' => 'move', 'target_date' => '2026-09-06',
        ])->assertRedirect();
        $output = $plan->fresh()->output;
        $this->assertSame('Rest', $output['days'][0]['title']);
        $this->assertSame('2026-09-05', $output['days'][0]['date']);
        $this->assertSame('Intervals', $output['days'][1]['title']);
        $this->assertSame(60, $output['total_duration_minutes']);
        $this->patch(route('coach.days.update', [$plan->id, '2026-09-06']), [
            'action' => 'move', 'target_date' => '2026-09-20',
        ])->assertUnprocessable();
    }

    public function test_users_cannot_change_someone_elses_plan_or_link_their_ride(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $plan = $this->plan($owner);
        $this->actingAs($other)->patch(route('coach.days.update', [$plan->id, '2026-09-05']), ['action' => 'complete'])->assertNotFound();
        $ride = Activity::factory()->for($other)->create();
        $this->actingAs($owner)->patch(route('coach.days.update', [$plan->id, '2026-09-05']), [
            'action' => 'link', 'activity_id' => $ride->id,
        ])->assertSessionHasErrors('activity_id');
    }

    public function test_manual_assignment_overrides_date_matching_and_invalid_timezones_fall_back(): void
    {
        $user = User::factory()->create();
        $plan = $this->plan($user);
        $ride = Activity::factory()->for($user)->create(['started_at' => '2026-09-06 12:00:00', 'timezone' => 'Athens']);
        $this->actingAs($user)->patch(route('coach.days.update', [$plan->id, '2026-09-05']), [
            'action' => 'link', 'activity_id' => $ride->id,
        ])->assertRedirect();
        $matches = app(PlanActivityMatcher::class)
            ->match(collect($plan->fresh()->output['days']), collect([$ride]));
        $this->assertSame($ride->id, $matches['2026-09-05']->first()->id);
        $this->assertCount(0, $matches['2026-09-06']);
        $this->assertSame('2026-09-06', $ride->localStartedAt()->toDateString());
    }
}
