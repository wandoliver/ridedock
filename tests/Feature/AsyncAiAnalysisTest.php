<?php

namespace Tests\Feature;

use App\Domain\Training\Contracts\GoalTrainingPlanner;
use App\Domain\Training\Contracts\TrainingAdvisor;
use App\Domain\Training\Contracts\TrainingBlockPlanner;
use App\Domain\Training\Contracts\WeeklyTrainingPlanner;
use App\Domain\Training\Data\TrainingAdvice;
use App\Domain\Training\Enums\AiAnalysisStatus;
use App\Jobs\GenerateActivityAnalysis;
use App\Jobs\GenerateGoalTrainingPlan;
use App\Jobs\GenerateTrainingBlock;
use App\Jobs\GenerateWeeklyTrainingPlan;
use App\Models\Activity;
use App\Models\AiAnalysis;
use App\Models\AthleteProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AsyncAiAnalysisTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** @return array<string, array{string, class-string, string, string}> */
    public static function requests(): array
    {
        return [
            'activity' => ['activity', TrainingAdvisor::class, 'analyzeActivity', 'activities.ai-analysis.store'],
            'roadmap' => ['goal_plan', GoalTrainingPlanner::class, 'generateGoalPlan', 'coach.goal-plan.store'],
            'block' => ['training_block', TrainingBlockPlanner::class, 'generateTrainingBlock', 'coach.training-block.store'],
            'week' => ['weekly_plan', WeeklyTrainingPlanner::class, 'generateWeeklyPlan', 'coach.weekly-plan.store'],
        ];
    }

    #[DataProvider('requests')]
    public function test_requests_queue_once_and_a_real_worker_completes_them(string $type, string $contract, string $method, string $route): void
    {
        config(['ai.queue_connection' => 'ai']);
        $planner = $this->mock($contract);
        $planner->shouldReceive('isConfigured')->andReturn(true);
        $planner->shouldReceive($method)->once()->andReturn(new TrainingAdvice(
            output: ['summary' => 'Worker result'],
            provider: 'test',
            model: 'test',
            responseId: 'test-response',
            inputTokens: 12,
            outputTokens: 34,
        ));
        $user = User::factory()->create();
        AthleteProfile::factory()->for($user)->create([
            'goal' => 'Long ride', 'goal_date' => now()->addMonths(6)->toDateString(),
            'available_days' => ['sunday'],
        ]);
        if ($type === 'training_block') {
            AiAnalysis::factory()->for($user)->create([
                'activity_id' => null, 'type' => 'goal_plan', 'status' => AiAnalysisStatus::Completed,
                'output' => ['phases' => []],
            ]);
        }
        $activity = Activity::factory()->for($user)->create();
        $url = route($route, $type === 'activity' ? [$activity] : []);
        $this->actingAs($user)->post($url)->assertRedirect()->assertSessionHas('status', __('ui.messages.ai_queued'));
        $analysis = $user->aiAnalyses()->where('type', $type)->firstOrFail();
        $this->assertSame(AiAnalysisStatus::Pending, $analysis->status);
        $this->assertNull($analysis->output);
        $this->assertDatabaseCount('jobs', 1);
        $this->actingAs($user)->post($url)->assertRedirect();
        $this->assertSame(1, $user->aiAnalyses()->where('type', $type)->count());
        $this->assertDatabaseCount('jobs', 1);
        $this->getJson(route('ai-analyses.status', $analysis))->assertExactJson(['status' => 'pending']);
        $page = $type === 'activity' ? route('activities.show', $activity) : route('coach.index');
        $this->withoutVite()->get($page)->assertOk()->assertSee('data-ai-status-url', false);

        $this->artisan('queue:work', ['connection' => 'ai', '--queue' => 'ai', '--once' => true])->assertExitCode(0);
        $analysis->refresh();
        $this->assertSame(AiAnalysisStatus::Completed, $analysis->status);
        $this->assertSame('Worker result', $analysis->output['summary']);
        $this->assertSame(12, $analysis->input_tokens);
        $this->assertNotNull($analysis->started_at);
        $this->assertNotNull($analysis->completed_at);
        $this->assertDatabaseEmpty('jobs');
        $jobClass = match ($type) {
            'activity' => GenerateActivityAnalysis::class,
            'goal_plan' => GenerateGoalTrainingPlan::class,
            'training_block' => GenerateTrainingBlock::class,
            'weekly_plan' => GenerateWeeklyTrainingPlan::class,
        };
        $job = new $jobClass($analysis->id);
        $this->app->call([$job, 'handle']);
        $job->failed(new \LogicException('Stale failure'));
        $this->assertSame(AiAnalysisStatus::Completed, $analysis->refresh()->status);
        $this->getJson(route('ai-analyses.status', $analysis))->assertExactJson(['status' => 'completed']);
    }

    public function test_worker_retries_and_eventually_exposes_only_a_safe_failure(): void
    {
        config(['ai.queue_connection' => 'ai']);
        $planner = $this->mock(WeeklyTrainingPlanner::class);
        $planner->shouldReceive('isConfigured')->andReturn(true);
        $planner->shouldReceive('generateWeeklyPlan')->times(3)->andThrow(new \LogicException('PRIVATE_PROVIDER_SECRET'));
        $user = User::factory()->create();
        AthleteProfile::factory()->for($user)->create(['available_days' => ['sunday']]);
        $this->actingAs($user)->post(route('coach.weekly-plan.store'))->assertRedirect();
        $analysis = $user->aiAnalyses()->firstOrFail();
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            DB::table('jobs')->update(['available_at' => now()->timestamp]);
            $this->artisan('queue:work', ['connection' => 'ai', '--queue' => 'ai', '--once' => true])->assertExitCode(0);
            $this->assertSame($attempt === 3 ? AiAnalysisStatus::Failed : AiAnalysisStatus::Processing, $analysis->refresh()->status);
        }
        $this->assertSame(__('ui.messages.week_failed_stored'), $analysis->error);
        $this->assertDatabaseEmpty('jobs');
        $this->assertDatabaseCount('failed_jobs', 1);
        $this->getJson(route('ai-analyses.status', $analysis))->assertExactJson(['status' => 'failed']);
        $this->withoutVite()->get(route('coach.index'))->assertOk()
            ->assertSee($analysis->error)->assertDontSee('PRIVATE_PROVIDER_SECRET');
    }

    public function test_coach_keeps_completed_plans_visible_during_regeneration_and_after_failure(): void
    {
        $planner = $this->mock(TrainingAdvisor::class);
        $planner->shouldReceive('isConfigured')->andReturn(true);
        $user = User::factory()->create();
        AiAnalysis::factory()->for($user)->create([
            'activity_id' => null, 'type' => 'goal_plan', 'status' => AiAnalysisStatus::Completed,
            'output' => ['goal_summary' => 'Existing roadmap', 'phases' => []],
        ]);
        $pending = AiAnalysis::factory()->for($user)->create([
            'activity_id' => null, 'type' => 'goal_plan', 'status' => AiAnalysisStatus::Pending,
        ]);
        $this->withoutVite()->actingAs($user)->get(route('coach.index'))->assertOk()
            ->assertSee('Existing roadmap')->assertSee(route('ai-analyses.status', $pending), false)
            ->assertSee('disabled', false);
        (new GenerateGoalTrainingPlan($pending->id))->failed(new \LogicException('PRIVATE_FAILURE'));
        $this->get(route('coach.index'))->assertOk()->assertSee('Existing roadmap')
            ->assertSee(__('ui.messages.roadmap_failed_stored'))->assertDontSee('PRIVATE_FAILURE')
            ->assertDontSee('data-ai-status-url', false);
    }

    public function test_status_is_owner_scoped_and_excludes_private_context(): void
    {
        $user = User::factory()->create();
        $analysis = AiAnalysis::factory()->for($user)->create(['context' => ['secret' => 'PRIVATE_CONTEXT']]);
        $this->getJson(route('ai-analyses.status', $analysis))->assertUnauthorized();
        $this->actingAs(User::factory()->create())->getJson(route('ai-analyses.status', $analysis))->assertNotFound();
        $this->actingAs($user)->getJson(route('ai-analyses.status', $analysis))
            ->assertExactJson(['status' => 'pending'])->assertHeader('Cache-Control', 'no-store, private');
    }
}
