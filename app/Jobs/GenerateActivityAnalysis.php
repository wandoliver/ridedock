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

    public int $tries = 0;

    public int $maxExceptions = 3;

    public int $deadline = 0;

    public int $timeout;

    public bool $failOnTimeout = true;

    public int $uniqueFor = 900;

    /** @var list<int> */
    public array $backoff = [15, 60, 180];

    public function __construct(public readonly string $analysisId)
    {
        $this->deadline = now()->addDay()->getTimestamp();
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

        try {
            $advice = app(\App\Domain\Operations\Services\AiUsage::class)->run(
                $analysis,
                fn () => $trainingAdvisor->analyzeActivity($analysis->context)
            );
        } catch (\App\Domain\Operations\Exceptions\WorkDeferred $deferred) {
            if ($this->job === null || $this->job instanceof \Illuminate\Queue\Jobs\SyncJob) {
                throw $deferred;
            }
            $this->release($deferred->retryAfter);
            return;
        }
        if ($advice === null) {
            return;
        }

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

        app(\App\Domain\Operations\Services\OperationalEvents::class)->record('ai', 'job_failed', $analysis->user_id);
        app()->setLocale($analysis->user()->value('locale') ?? config('app.locale'));
        $analysis->update([
            'status' => AiAnalysisStatus::Failed,
            'error' => __('ui.messages.analysis_error_stored'),
            'completed_at' => now(),
        ]);
    }

    public function retryUntil(): \DateTimeInterface
    {
        return $this->deadline > 0 ? \Carbon\CarbonImmutable::createFromTimestamp($this->deadline) : \Carbon\CarbonImmutable::now()->addDays(1);
    }

    public function uniqueId(): string
    {
        return $this->analysisId;
    }
}
