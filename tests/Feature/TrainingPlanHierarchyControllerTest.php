<?php

namespace Tests\Feature;

use App\Domain\Training\Contracts\GoalTrainingPlanner;
use App\Domain\Training\Contracts\TrainingBlockPlanner;
use App\Domain\Training\Data\TrainingAdvice;
use App\Domain\Training\Enums\AiAnalysisStatus;
use App\Models\AthleteProfile;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class TrainingPlanHierarchyControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_user_can_generate_a_goal_roadmap_and_a_four_week_block(): void
    {
        CarbonImmutable::setTestNow('2026-08-31T12:00:00Z');
        $this->app->instance(GoalTrainingPlanner::class, $this->goalPlanner());
        $this->app->instance(TrainingBlockPlanner::class, $this->blockPlanner());
        $user = User::factory()->create();
        AthleteProfile::factory()->for($user)->create([
            'goal' => '160 Kilometer kontrolliert fahren',
            'goal_date' => '2026-12-31',
            'weekly_hours' => 7,
            'available_days' => ['tuesday', 'thursday', 'sunday'],
        ]);

        $this->actingAs($user)
            ->post(route('coach.goal-plan.store'))
            ->assertRedirect(route('coach.index').'#goal-plan')
            ->assertSessionHas('status', 'Deine Ziel-Roadmap ist fertig.');

        $goalPlan = $user->aiAnalyses()->where('type', 'goal_plan')->firstOrFail();
        $this->assertSame(AiAnalysisStatus::Completed, $goalPlan->status);
        $this->assertSame('goal-plan-v6', $goalPlan->prompt_version);
        $this->assertSame('2026-09-01', $goalPlan->context['plan_start']);
        $this->assertSame('2026-12-31', $goalPlan->context['plan_end']);

        $this->actingAs($user)
            ->post(route('coach.training-block.store'))
            ->assertRedirect(route('coach.index').'#training-block')
            ->assertSessionHas('status', 'Dein Vier-Wochen-Block ist fertig.');

        $block = $user->aiAnalyses()->where('type', 'training_block')->firstOrFail();
        $this->assertSame(AiAnalysisStatus::Completed, $block->status);
        $this->assertSame('training-block-v6', $block->prompt_version);
        $this->assertSame($goalPlan->id, $block->context['goal_plan_analysis_id']);
        $this->assertSame($goalPlan->output, $block->context['goal_plan']);
        $this->assertCount(4, $block->output['weeks']);

        $this->withoutVite()
            ->actingAs($user)
            ->get(route('coach.index'))
            ->assertOk()
            ->assertSee('Sicher zur 160-Kilometer-Tour')
            ->assertSee('Vier-Wochen-Block')
            ->assertSee('Grundlage ausbauen')
            ->assertSee('Wenn das Leben dazwischenkommt');
    }

    public function test_goal_plan_requires_a_future_dated_goal_and_block_requires_a_roadmap(): void
    {
        CarbonImmutable::setTestNow('2026-08-31T12:00:00Z');
        $this->app->instance(GoalTrainingPlanner::class, $this->goalPlanner());
        $this->app->instance(TrainingBlockPlanner::class, $this->blockPlanner());
        $user = User::factory()->create();
        AthleteProfile::factory()->for($user)->create([
            'goal' => null,
            'goal_date' => null,
        ]);

        $this->actingAs($user)
            ->post(route('coach.goal-plan.store'))
            ->assertRedirect(route('settings.training'))
            ->assertSessionHas('analysis_error');

        $this->actingAs($user)
            ->post(route('coach.training-block.store'))
            ->assertRedirect(route('coach.index').'#goal-plan')
            ->assertSessionHas('analysis_error', 'Erstelle zuerst deine Ziel-Roadmap.');

        $this->assertDatabaseEmpty('ai_analyses');
    }

    private function goalPlanner(): GoalTrainingPlanner
    {
        return new class implements GoalTrainingPlanner
        {
            public function isConfigured(): bool
            {
                return true;
            }

            public function generateGoalPlan(array $context): TrainingAdvice
            {
                return new TrainingAdvice(
                    output: [
                        'goal_summary' => 'Sicher zur 160-Kilometer-Tour',
                        'overview' => 'Die Belastung wird schrittweise aufgebaut.',
                        'plan_start' => $context['plan_start'],
                        'plan_end' => $context['plan_end'],
                        'phases' => [[
                            'name' => 'Aufbau',
                            'start_date' => $context['plan_start'],
                            'end_date' => $context['plan_end'],
                            'focus' => 'Ausdauer und Belastungsverträglichkeit entwickeln.',
                            'success_marker' => 'Lange Fahrten bleiben kontrolliert.',
                        ]],
                        'principles' => ['Konservativ steigern.'],
                        'cautions' => [],
                    ],
                    provider: 'openai',
                    model: 'test-model',
                    responseId: 'goal-response',
                    inputTokens: 100,
                    outputTokens: 200,
                );
            }
        };
    }

    private function blockPlanner(): TrainingBlockPlanner
    {
        return new class implements TrainingBlockPlanner
        {
            public function isConfigured(): bool
            {
                return true;
            }

            public function generateTrainingBlock(array $context): TrainingAdvice
            {
                return new TrainingAdvice(
                    output: [
                        'overview' => 'Drei Aufbauwochen und eine Entlastung.',
                        'block_goal' => 'Grundlage ausbauen',
                        'phase_name' => 'Aufbau',
                        'block_start' => $context['block_start'],
                        'block_end' => $context['block_end'],
                        'weeks' => array_map(
                            fn (array $week): array => [
                                'week' => $week['week'],
                                'week_start' => $week['start'],
                                'week_end' => $week['end'],
                                'focus' => 'Ruhige Ausdauer',
                                'load_level' => $week['week'] === 4 ? 'recovery' : 'build',
                                'target_duration_minutes' => $week['week'] === 4 ? 240 : 360,
                                'key_sessions' => ['Lange ruhige Ausfahrt'],
                            ],
                            $context['block_weeks'],
                        ),
                        'adaptation_rules' => ['Bei hoher Müdigkeit Umfang reduzieren.'],
                        'cautions' => [],
                    ],
                    provider: 'openai',
                    model: 'test-model',
                    responseId: 'block-response',
                    inputTokens: 120,
                    outputTokens: 220,
                );
            }
        };
    }
}
