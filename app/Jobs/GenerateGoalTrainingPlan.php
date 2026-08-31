<?php

namespace App\Jobs;

use App\Domain\Training\Contracts\GoalTrainingPlanner;
use App\Domain\Training\Enums\AiAnalysisStatus;
use App\Models\AiAnalysis;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class GenerateGoalTrainingPlan
{
    use Queueable;

    public function __construct(public readonly string $analysisId) {}

    public function handle(GoalTrainingPlanner $trainingPlanner): void
    {
        $analysis = AiAnalysis::query()->findOrFail($this->analysisId);

        $analysis->update([
            'status' => AiAnalysisStatus::Processing,
            'started_at' => now(),
            'error' => null,
        ]);

        $advice = $trainingPlanner->generateGoalPlan($analysis->context);

        $analysis->update([
            'status' => AiAnalysisStatus::Completed,
            'provider' => $advice->provider,
            'model' => $advice->model,
            'provider_response_id' => $advice->responseId,
            'output' => $advice->output,
            'input_tokens' => $advice->inputTokens,
            'output_tokens' => $advice->outputTokens,
            'completed_at' => now(),
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        AiAnalysis::query()->whereKey($this->analysisId)->update([
            'status' => AiAnalysisStatus::Failed,
            'error' => 'Die Ziel-Roadmap konnte nicht erstellt werden.',
            'completed_at' => now(),
        ]);
    }
}
