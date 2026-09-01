<?php

namespace App\Http\Controllers;

use App\Domain\Training\Contracts\WeeklyTrainingPlanner;
use App\Domain\Training\Enums\AiAnalysisStatus;
use App\Domain\Training\Services\WeeklyTrainingContextBuilder;
use App\Jobs\GenerateWeeklyTrainingPlan;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class WeeklyTrainingPlanController extends Controller
{
    public function __invoke(
        Request $request,
        WeeklyTrainingContextBuilder $contextBuilder,
        WeeklyTrainingPlanner $trainingPlanner,
    ): RedirectResponse {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(404);
        }

        $user->loadMissing('athleteProfile');
        $profile = $user->athleteProfile;

        if (! $trainingPlanner->isConfigured()) {
            return back()->with('analysis_error', __('ui.messages.ai_not_configured'));
        }

        if (($profile->available_days ?? []) === []) {
            return redirect()
                ->route('settings.training')
                ->with('analysis_error', __('ui.messages.choose_days'));
        }

        $analysis = $user->aiAnalyses()->create([
            'activity_id' => null,
            'type' => 'weekly_plan',
            'status' => AiAnalysisStatus::Pending,
            'context_schema_version' => WeeklyTrainingContextBuilder::SCHEMA_VERSION,
            'prompt_version' => 'weekly-plan-v2',
            'context' => $contextBuilder->build($user),
        ]);

        try {
            GenerateWeeklyTrainingPlan::dispatchSync($analysis->id);
        } catch (Throwable $exception) {
            report($exception);

            $analysis->update([
                'status' => AiAnalysisStatus::Failed,
                'error' => __('ui.messages.week_failed_stored'),
                'completed_at' => now(),
            ]);

            return redirect()
                ->to(route('coach.index').'#weekly-plan')
                ->with('analysis_error', __('ui.messages.week_failed'));
        }

        return redirect()
            ->to(route('coach.index').'#weekly-plan')
            ->with('status', __('ui.messages.week_complete'));
    }
}
