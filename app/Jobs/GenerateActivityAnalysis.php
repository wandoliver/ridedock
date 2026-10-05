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

    public int $timeout;

    public bool $failOnTimeout = true;

    public int $uniqueFor = 900;

    /** @var list<int> */
    public array $backoff = [15, 60, 180];

    public function __construct(public readonly string $analysisId)
    {
        $this->timeout = max(120, (int) config('ai.providers.openai.timeout_seconds') + 10);
    }

    public function handle(TrainingAdvisor $trainingAdvisor): void
    {
        $analysis = AiAnalysis::query()->find($this->analysisId);
        if ($analysis === null) {
            return;
        }
        app()->setLocale($analysis->user()->value('locale') ?? config('app.locale'));

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
        $analysis = AiAnalysis::query()->find($this->analysisId);

        if ($analysis === null || $analysis->status === AiAnalysisStatus::Completed) {
            return;
        }

        app()->setLocale($analysis->user()->value('locale') ?? config('app.locale'));
        $analysis->update([
            'status' => AiAnalysisStatus::Failed,
            'error' => __('ui.messages.analysis_error_stored'),
            'completed_at' => now(),
        ]);
    }

    public function uniqueId(): string
    {
        return $this->analysisId;
    }
}
