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
            return back()->with('analysis_error', 'Die KI-Analyse ist noch nicht konfiguriert.');
        }

        $isAlreadyRunning = $model->aiAnalyses()
            ->whereIn('status', [AiAnalysisStatus::Pending, AiAnalysisStatus::Processing])
            ->exists();

        if ($isAlreadyRunning) {
            return back()->with('status', 'Für diese Fahrt läuft bereits eine Analyse.');
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
                'error' => 'Die Analyse konnte nicht abgeschlossen werden.',
                'completed_at' => now(),
            ]);

            return back()->with('analysis_error', 'Die Fahrtanalyse konnte nicht abgeschlossen werden. Bitte versuche es erneut.');
        }

        return back()->with('status', 'Die Fahrtanalyse ist abgeschlossen.');
    }
}
