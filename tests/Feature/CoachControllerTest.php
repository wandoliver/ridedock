<?php

namespace Tests\Feature;

use App\Domain\Training\Contracts\TrainingAdvisor;
use App\Domain\Training\Data\TrainingAdvice;
use App\Domain\Training\Enums\AiAnalysisStatus;
use App\Models\Activity;
use App\Models\AiAnalysis;
use App\Models\AthleteProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use LogicException;
use Tests\TestCase;

class CoachControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('coach.index'))->assertRedirect(route('login'));
    }

    public function test_user_can_choose_only_their_activities_for_analysis(): void
    {
        $this->app->instance(TrainingAdvisor::class, new class implements TrainingAdvisor
        {
            public function isConfigured(): bool
            {
                return true;
            }

            public function analyzeActivity(array $context): TrainingAdvice
            {
                throw new LogicException('No provider request expected.');
            }
        });
        $user = User::factory()->create();
        AthleteProfile::factory()->for($user)->create([
            'goal' => 'Dolomitenrunde im Juni',
            'weekly_hours' => 8,
        ]);
        $activity = Activity::factory()->for($user)->create(['name' => 'Eigene Fahrt']);
        AiAnalysis::factory()->for($user)->for($activity)->create([
            'status' => AiAnalysisStatus::Completed,
        ]);
        AiAnalysis::factory()->for($user)->create([
            'activity_id' => null,
            'type' => 'weekly_plan',
            'status' => AiAnalysisStatus::Completed,
            'output' => [
                'overview' => 'Eine ausgewogene Trainingswoche.',
                'weekly_goal' => 'Ausdauer festigen',
                'total_duration_minutes' => 240,
                'days' => [[
                    'date' => '2026-08-31',
                    'title' => 'Lockere Ausfahrt',
                    'type' => 'ride',
                    'duration_minutes' => 60,
                    'intensity' => 'Leicht',
                    'details' => 'Ruhig rollen.',
                    'rationale' => 'Aerobe Grundlage.',
                ]],
                'data_gaps' => [],
                'cautions' => [],
            ],
            'completed_at' => now(),
        ]);
        Activity::factory()->create(['name' => 'Fremde Fahrt']);

        $this->withoutVite()
            ->actingAs($user)
            ->get(route('coach.index'))
            ->assertOk()
            ->assertSee('Vom Ziel zur nächsten Einheit')
            ->assertSee('Fahrt analysieren')
            ->assertSee('Ziel-Roadmap')
            ->assertSee('Trainingsblock')
            ->assertSee('Sieben-Tage-Plan')
            ->assertSee('Plan neu erstellen')
            ->assertSee('Ausdauer festigen')
            ->assertSee('Lockere Ausfahrt')
            ->assertSee('data-weekly-plan-panel', false)
            ->assertSee('Geplanter Umfang')
            ->assertSee('Warum:')
            ->assertSee('Dolomitenrunde im Juni')
            ->assertSee('Eigene Fahrt')
            ->assertSee('Analysiert')
            ->assertSee(route('activities.show', $activity).'#ai-analysis-heading', false)
            ->assertDontSee('KI-Verbindung')
            ->assertDontSee('Noch nicht freigeschaltet')
            ->assertSee(route('coach.weekly-plan.store'), false)
            ->assertDontSee('Fremde Fahrt');
    }

    public function test_dashboard_has_a_visible_coach_entry(): void
    {
        $user = User::factory()->create();

        $this->withoutVite()
            ->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Coach öffnen')
            ->assertSee(route('coach.index'), false);
    }
}
