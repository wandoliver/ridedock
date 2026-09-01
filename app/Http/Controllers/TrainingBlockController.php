<?php

namespace App\Http\Controllers;

use App\Domain\Training\Contracts\TrainingBlockPlanner;
use App\Domain\Training\Enums\AiAnalysisStatus;
use App\Domain\Training\Services\TrainingBlockContextBuilder;
use App\Jobs\GenerateTrainingBlock;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class TrainingBlockController extends Controller
{
    public function __invoke(
        Request $request,
        TrainingBlockContextBuilder $contextBuilder,
        TrainingBlockPlanner $trainingPlanner,
    ): RedirectResponse {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(404);
        }

        if (! $trainingPlanner->isConfigured()) {
            return back()->with('analysis_error', __('ui.messages.ai_not_configured'));
        }

        $goalPlan = $user->aiAnalyses()
            ->where('type', 'goal_plan')
            ->where('status', AiAnalysisStatus::Completed)
            ->latest()
            ->first();

        if ($goalPlan === null) {
            return redirect()
                ->to(route('coach.index').'#goal-plan')
                ->with('analysis_error', __('ui.messages.roadmap_first'));
        }

        $analysis = $user->aiAnalyses()->create([
            'activity_id' => null,
            'type' => 'training_block',
            'status' => AiAnalysisStatus::Pending,
            'context_schema_version' => TrainingBlockContextBuilder::SCHEMA_VERSION,
            'prompt_version' => 'training-block-v1',
            'context' => $contextBuilder->build($user),
        ]);

        try {
            GenerateTrainingBlock::dispatchSync($analysis->id);
        } catch (Throwable $exception) {
            report($exception);

            $analysis->update([
                'status' => AiAnalysisStatus::Failed,
                'error' => __('ui.messages.block_failed_stored'),
                'completed_at' => now(),
            ]);

            return redirect()
                ->to(route('coach.index').'#training-block')
                ->with('analysis_error', __('ui.messages.block_failed'));
        }

        return redirect()
            ->to(route('coach.index').'#training-block')
            ->with('status', __('ui.messages.block_complete'));
    }
}
