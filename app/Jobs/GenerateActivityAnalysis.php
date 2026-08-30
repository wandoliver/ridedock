<?php

namespace App\Jobs;

use App\Domain\Training\Contracts\TrainingAdvisor;
use App\Domain\Training\Enums\AiAnalysisStatus;
use App\Models\AiAnalysis;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class GenerateActivityAnalysis implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public int $uniqueFor = 600;

    /** @var list<int> */
    public array $backoff = [15, 60, 180];

    public function __construct(public readonly string $analysisId) {}

    public function handle(TrainingAdvisor $trainingAdvisor): void
    {
        $analysis = AiAnalysis::query()->findOrFail($this->analysisId);

        if ($analysis->status === AiAnalysisStatus::Completed) {
            return;
        }

        $analysis->update([
            'status' => AiAnalysisStatus::Processing,
            'started_at' => now(),
            'error' => null,
        ]);

        $advice = $trainingAdvisor->analyzeActivity($analysis->context);

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
            'error' => 'Die Analyse konnte nicht abgeschlossen werden.',
            'completed_at' => now(),
        ]);
    }

    public function uniqueId(): string
    {
        return $this->analysisId;
    }
}
