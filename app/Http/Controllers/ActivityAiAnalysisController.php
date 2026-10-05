<?php

namespace App\Http\Controllers;

use App\Domain\Training\Contracts\TrainingAdvisor;
use App\Domain\Training\Enums\AiAnalysisStatus;
use App\Domain\Training\Services\ActivityTrainingContextBuilder;
use App\Domain\Training\Services\ReserveAiAnalysis;
use App\Jobs\GenerateActivityAnalysis;
use App\Models\Activity;
use App\Models\User;
use App\Support\ActivityNavigation;
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
        $redirect = redirect()->route('activities.show', ActivityNavigation::detailParameters($request, $model->id))->withFragment('coach');

        if (! $trainingAdvisor->isConfigured()) {
            return $redirect->with('analysis_error', __('ui.messages.ai_not_configured'));
        }

        $isAlreadyRunning = $model->aiAnalyses()
            ->whereIn('status', [AiAnalysisStatus::Pending, AiAnalysisStatus::Processing])
            ->exists();

        if ($isAlreadyRunning) {
            return $redirect->with('status', __('ui.messages.analysis_running'));
        }

        $context = $contextBuilder->build($model);
        $analysis = app(ReserveAiAnalysis::class)->reserve($user, [
            'activity_id' => $model->id,
            'user_id' => $user->id,
            'type' => 'activity',
            'status' => AiAnalysisStatus::Pending,
            'context_schema_version' => ActivityTrainingContextBuilder::SCHEMA_VERSION,
            'prompt_version' => 'activity-v8',
            'context' => $context,
        ]);

        try {
            if ($analysis->wasRecentlyCreated) {
                GenerateActivityAnalysis::dispatch($analysis->id)
                    ->onConnection(config('ai.queue_connection'))->onQueue('ai');
            }
        } catch (Throwable $exception) {
            report($exception);

            $analysis->update([
                'status' => AiAnalysisStatus::Failed,
                'error' => __('ui.messages.analysis_error_stored'),
                'completed_at' => now(),
            ]);

            return $redirect->with('analysis_error', __('ui.messages.ride_analysis_failed'));
        }

        return $redirect->with('status', __($analysis->refresh()->status === AiAnalysisStatus::Completed
            ? 'ui.messages.ride_analysis_complete' : 'ui.messages.ai_queued'));
    }
}
