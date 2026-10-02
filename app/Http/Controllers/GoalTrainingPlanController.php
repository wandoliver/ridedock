<?php

namespace App\Http\Controllers;

use App\Domain\Training\Contracts\GoalTrainingPlanner;
use App\Domain\Training\Enums\AiAnalysisStatus;
use App\Domain\Training\Services\GoalTrainingContextBuilder;
use App\Domain\Training\Services\ReserveAiAnalysis;
use App\Jobs\GenerateGoalTrainingPlan;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class GoalTrainingPlanController extends Controller
{
    public function __invoke(
        Request $request,
        GoalTrainingContextBuilder $contextBuilder,
        GoalTrainingPlanner $trainingPlanner,
    ): RedirectResponse {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(404);
        }

        $user->loadMissing('athleteProfile');
        $profile = $user->athleteProfile;
        $tomorrow = CarbonImmutable::today(config('app.timezone'))->addDay();

        if (! $trainingPlanner->isConfigured()) {
            return back()->with('analysis_error', __('ui.messages.ai_not_configured'));
        }

        if ($profile?->goal === null || trim($profile->goal) === ''
            || $profile->goal_date === null || $profile->goal_date->isBefore($tomorrow)) {
            return redirect()
                ->route('settings.training')
                ->with('analysis_error', __('ui.messages.goal_required'));
        }

        $analysis = app(ReserveAiAnalysis::class)->reserve($user, [
            'activity_id' => null,
            'type' => 'goal_plan',
            'status' => AiAnalysisStatus::Pending,
            'context_schema_version' => GoalTrainingContextBuilder::SCHEMA_VERSION,
            'prompt_version' => 'goal-plan-v6',
            'context' => $contextBuilder->build($user, $tomorrow),
        ]);

        try {
            if ($analysis->wasRecentlyCreated) {
                GenerateGoalTrainingPlan::dispatch($analysis->id)
                    ->onConnection(config('ai.queue_connection'))->onQueue('ai');
            }
        } catch (Throwable $exception) {
            report($exception);

            $analysis->update([
                'status' => AiAnalysisStatus::Failed,
                'error' => __('ui.messages.roadmap_failed_stored'),
                'completed_at' => now(),
            ]);

            return redirect()
                ->to(route('coach.index').'#goal-plan')
                ->with('analysis_error', __('ui.messages.roadmap_failed'));
        }

        return redirect()
            ->to(route('coach.index').'#goal-plan')
            ->with('status', __($analysis->refresh()->status === AiAnalysisStatus::Completed
            ? 'ui.messages.roadmap_complete' : 'ui.messages.ai_queued'));
    }
}
