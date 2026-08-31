<?php

namespace App\Jobs;

use App\Domain\Training\Contracts\TrainingBlockPlanner;
use App\Domain\Training\Enums\AiAnalysisStatus;
use App\Models\AiAnalysis;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class GenerateTrainingBlock
{
    use Queueable;

    public function __construct(public readonly string $analysisId) {}

    public function handle(TrainingBlockPlanner $trainingPlanner): void
    {
        $analysis = AiAnalysis::query()->findOrFail($this->analysisId);

        $analysis->update([
            'status' => AiAnalysisStatus::Processing,
            'started_at' => now(),
            'error' => null,
        ]);

        $advice = $trainingPlanner->generateTrainingBlock($analysis->context);

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
            'error' => 'Der Trainingsblock konnte nicht erstellt werden.',
            'completed_at' => now(),
        ]);
    }
}
