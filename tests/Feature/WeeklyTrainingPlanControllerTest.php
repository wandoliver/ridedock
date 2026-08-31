<?php

namespace Tests\Feature;

use App\Domain\Training\Contracts\WeeklyTrainingPlanner;
use App\Domain\Training\Data\TrainingAdvice;
use App\Domain\Training\Enums\AiAnalysisStatus;
use App\Models\Activity;
use App\Models\AthleteProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use LogicException;
use Tests\TestCase;

class WeeklyTrainingPlanControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_user_can_generate_a_seven_day_plan_directly(): void
    {
        $this->app->instance(WeeklyTrainingPlanner::class, $this->planner(true));
        $user = User::factory()->create();
        AthleteProfile::factory()->for($user)->create([
            'goal' => 'Eine lange Tour entspannt fahren',
            'weekly_hours' => null,
            'available_days' => ['tuesday', 'thursday', 'sunday'],
        ]);
        Activity::factory()->for($user)->create([
            'notes' => 'PRIVATE_WEEKLY_NOTE',
            'summary_polyline' => 'PRIVATE_WEEKLY_ROUTE',
        ]);

        $this->actingAs($user)
            ->post(route('coach.weekly-plan.store'))
            ->assertRedirect(route('coach.index').'#weekly-plan')
            ->assertSessionHas('status', 'Dein Sieben-Tage-Plan ist fertig.');

        $plan = $user->aiAnalyses()->where('type', 'weekly_plan')->firstOrFail();
        $this->assertNull($plan->activity_id);
        $this->assertSame(AiAnalysisStatus::Completed, $plan->status);
        $this->assertSame('weekly-plan-v2', $plan->prompt_version);
        $this->assertCount(7, $plan->output['days']);
        $encodedContext = json_encode($plan->context, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('PRIVATE_WEEKLY_NOTE', $encodedContext);
        $this->assertStringNotContainsString('PRIVATE_WEEKLY_ROUTE', $encodedContext);
        $this->assertFalse($plan->context['privacy']['gps_coordinates_included']);
    }

    public function test_plan_requires_configuration_and_scheduling_profile_fields(): void
    {
        $user = User::factory()->create();
        AthleteProfile::factory()->for($user)->create([
            'weekly_hours' => null,
            'available_days' => [],
        ]);
        $this->app->instance(WeeklyTrainingPlanner::class, $this->planner(true));

        $this->actingAs($user)
            ->post(route('coach.weekly-plan.store'))
            ->assertRedirect(route('settings.training'))
            ->assertSessionHas('analysis_error');

        $this->assertDatabaseEmpty('ai_analyses');

        $this->app->instance(WeeklyTrainingPlanner::class, $this->planner(false));
        $completeUser = User::factory()->create();
        AthleteProfile::factory()->for($completeUser)->create([
            'weekly_hours' => null,
            'available_days' => ['sunday'],
        ]);

        $this->actingAs($completeUser)
            ->post(route('coach.weekly-plan.store'))
            ->assertRedirect()
            ->assertSessionHas('analysis_error', 'Die KI-Analyse ist noch nicht konfiguriert.');
    }

    public function test_provider_failure_is_recorded_without_exposing_details(): void
    {
        $this->app->instance(WeeklyTrainingPlanner::class, new class implements WeeklyTrainingPlanner
        {
            public function isConfigured(): bool
            {
                return true;
            }

            public function generateWeeklyPlan(array $context): TrainingAdvice
            {
                throw new LogicException('PRIVATE_WEEKLY_PROVIDER_ERROR');
            }
        });
        $user = User::factory()->create();
        AthleteProfile::factory()->for($user)->create([
            'weekly_hours' => 6,
            'available_days' => ['sunday'],
        ]);

        $this->actingAs($user)
            ->post(route('coach.weekly-plan.store'))
            ->assertRedirect(route('coach.index').'#weekly-plan')
            ->assertSessionHas('analysis_error');

        $plan = $user->aiAnalyses()->firstOrFail();
        $this->assertSame(AiAnalysisStatus::Failed, $plan->status);
        $this->assertSame('Der Wochenplan konnte nicht erstellt werden.', $plan->error);
        $this->assertStringNotContainsString('PRIVATE_WEEKLY_PROVIDER_ERROR', $plan->error);
    }

    private function planner(bool $configured): WeeklyTrainingPlanner
    {
        return new class($configured) implements WeeklyTrainingPlanner
        {
            public function __construct(private readonly bool $configured) {}

            public function isConfigured(): bool
            {
                return $this->configured;
            }

            public function generateWeeklyPlan(array $context): TrainingAdvice
            {
                return new TrainingAdvice(
                    output: [
                        'overview' => 'Eine ausgewogene Woche.',
                        'weekly_goal' => 'Ausdauer festigen',
                        'total_duration_minutes' => 240,
                        'days' => array_map(
                            fn (array $day): array => [
                                'date' => $day['date'],
                                'title' => $day['available_for_training'] ? 'Lockere Ausfahrt' : 'Ruhetag',
                                'type' => $day['available_for_training'] ? 'ride' : 'rest',
                                'duration_minutes' => $day['available_for_training'] ? 60 : null,
                                'intensity' => $day['available_for_training'] ? 'Leicht' : 'Keine',
                                'details' => 'Nach Tagesform gestalten.',
                                'rationale' => 'Belastung und Erholung verteilen.',
                            ],
                            $context['plan_days'],
                        ),
                        'data_gaps' => [],
                        'cautions' => [],
                    ],
                    provider: 'openai',
                    model: 'test-model',
                    responseId: 'response-weekly-123',
                    inputTokens: 200,
                    outputTokens: 300,
                );
            }
        };
    }
}
