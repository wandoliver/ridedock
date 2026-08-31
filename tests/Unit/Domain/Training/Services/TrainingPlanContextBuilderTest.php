<?php

namespace Tests\Unit\Domain\Training\Services;

use App\Domain\Training\Enums\AiAnalysisStatus;
use App\Domain\Training\Services\GoalTrainingContextBuilder;
use App\Domain\Training\Services\TrainingBlockContextBuilder;
use App\Domain\Training\Services\WeeklyTrainingContextBuilder;
use App\Models\AiAnalysis;
use App\Models\AthleteProfile;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class TrainingPlanContextBuilderTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_goal_and_block_contexts_share_history_and_preserve_the_plan_hierarchy(): void
    {
        $user = User::factory()->create();
        AthleteProfile::factory()->for($user)->create([
            'goal' => 'Alpenrunde fahren',
            'goal_date' => '2027-06-30',
            'weekly_hours' => 8,
        ]);
        $start = CarbonImmutable::parse('2027-01-01');
        $goalContext = app(GoalTrainingContextBuilder::class)->build($user, $start);

        $this->assertSame('goal_training_plan', $goalContext['purpose']);
        $this->assertSame('2027-06-30', $goalContext['plan_end']);
        $this->assertArrayHasKey('recent_training', $goalContext);

        $goalOutput = [
            'goal_summary' => 'Alpenrunde',
            'plan_start' => '2027-01-01',
            'plan_end' => '2027-06-30',
            'phases' => [],
        ];
        $goalAnalysis = AiAnalysis::factory()->for($user)->create([
            'activity_id' => null,
            'type' => 'goal_plan',
            'status' => AiAnalysisStatus::Completed,
            'output' => $goalOutput,
        ]);

        $blockContext = app(TrainingBlockContextBuilder::class)->build($user, $start);
        $this->assertSame('four_week_training_block', $blockContext['purpose']);
        $this->assertSame('2027-01-28', $blockContext['block_end']);
        $this->assertCount(4, $blockContext['block_weeks']);
        $this->assertSame($goalAnalysis->id, $blockContext['goal_plan_analysis_id']);
        $this->assertSame($goalOutput, $blockContext['goal_plan']);

        $blockOutput = ['block_goal' => 'Grundlage ausbauen', 'weeks' => []];
        AiAnalysis::factory()->for($user)->create([
            'activity_id' => null,
            'type' => 'training_block',
            'status' => AiAnalysisStatus::Completed,
            'context' => ['goal_plan_analysis_id' => $goalAnalysis->id],
            'output' => $blockOutput,
        ]);

        $weeklyContext = app(WeeklyTrainingContextBuilder::class)->build($user, $start);
        $this->assertSame($goalOutput, $weeklyContext['strategy']['goal_plan']);
        $this->assertSame($blockOutput, $weeklyContext['strategy']['training_block']);

        $newGoalOutput = ['goal_summary' => 'Neues Ziel', 'phases' => []];
        AiAnalysis::factory()->for($user)->create([
            'activity_id' => null,
            'type' => 'goal_plan',
            'status' => AiAnalysisStatus::Completed,
            'output' => $newGoalOutput,
            'created_at' => now()->addMinute(),
        ]);

        $updatedWeeklyContext = app(WeeklyTrainingContextBuilder::class)->build($user, $start);
        $this->assertSame($newGoalOutput, $updatedWeeklyContext['strategy']['goal_plan']);
        $this->assertNull($updatedWeeklyContext['strategy']['training_block']);
    }
}
