<?php

namespace App\Http\Controllers;

use App\Domain\Training\Contracts\TrainingAdvisor;
use App\Domain\Training\Enums\AiAnalysisStatus;
use App\Domain\Training\Services\ActivityTrainingContextBuilder;
use App\Jobs\GenerateActivityAnalysis;
use App\Models\Activity;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class ActivityAiAnalysisController extends Controller
{
    public function __invoke(
        Request $request,
        string $activity,
        ActivityTrainingContextBuilder $contextBuilder,
        TrainingAdvisor $trainingAdvisor,
    ): RedirectResponse {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(404);
        }

        $model = Activity::query()->whereBelongsTo($user)->findOrFail($activity);

        if (! $trainingAdvisor->isConfigured()) {
            return back()->with('analysis_error', __('ui.messages.ai_not_configured'));
        }

        $isAlreadyRunning = $model->aiAnalyses()
            ->whereIn('status', [AiAnalysisStatus::Pending, AiAnalysisStatus::Processing])
            ->exists();

        if ($isAlreadyRunning) {
            return back()->with('status', __('ui.messages.analysis_running'));
        }

        $context = $contextBuilder->build($model);
        $analysis = $model->aiAnalyses()->create([
            'user_id' => $user->id,
            'type' => 'activity',
            'status' => AiAnalysisStatus::Pending,
            'context_schema_version' => ActivityTrainingContextBuilder::SCHEMA_VERSION,
            'prompt_version' => 'activity-v2',
            'context' => $context,
        ]);

        try {
            GenerateActivityAnalysis::dispatchSync($analysis->id);
        } catch (Throwable $exception) {
            report($exception);

            $analysis->update([
                'status' => AiAnalysisStatus::Failed,
                'error' => __('ui.messages.analysis_error_stored'),
                'completed_at' => now(),
            ]);

            return back()->with('analysis_error', __('ui.messages.ride_analysis_failed'));
        }

        return back()->with('status', __('ui.messages.ride_analysis_complete'));
    }
}
