<?php

namespace App\Domain\Operations\Services;

use App\Domain\Operations\Exceptions\WorkDeferred;
use App\Domain\Training\Data\TrainingAdvice;
use App\Models\AiAnalysis;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;

final class AiUsage
{
    private ?int $attempt = null;
    private bool $received = false;
    private ?string $month = null;

    /** @param callable(): TrainingAdvice $request */
    public function run(AiAnalysis $analysis, callable $request): ?TrainingAdvice
    {
        $attempt = DB::transaction(function () use ($analysis): ?int {
            DB::table('operation_mutexes')->where('name', 'ai')->lockForUpdate()->first();
            if (! DB::table('users')->where('id', $analysis->user_id)->lockForUpdate()->first(['id'])) {
                return null;
            }
            $current = AiAnalysis::query()->whereKey($analysis->id)->lockForUpdate()->first();
            if ($current === null || $current->status === \App\Domain\Training\Enums\AiAnalysisStatus::Completed) {
                return null;
            }
            $lease = max(180, (int) config('ai.providers.openai.timeout_seconds') + 60);
            $running = DB::table('ai_usage_attempts')->where('status', 'running')->where('created_at', '>=', now()->subSeconds($lease));
            if ((clone $running)->where('analysis_id', $analysis->id)->exists()
                || $running->count() >= max(1, (int) config('operations.ai.concurrency'))
                || DB::table('ai_usage_attempts')->where('created_at', '>', now()->subMinute())->count() >= max(1, (int) config('operations.ai.requests_per_minute'))) {
                throw new WorkDeferred(15);
            }
            $current->update(['status' => \App\Domain\Training\Enums\AiAnalysisStatus::Processing, 'started_at' => now(), 'error' => null]);
            $month = now()->format('Y-m');
            $this->month = $month;
            DB::table('ai_spend_months')->insertOrIgnore(['month' => $month]);
            DB::table('ai_spend_months')->where('month', $month)->increment('unknown_costs');
            return DB::table('ai_usage_attempts')->insertGetId([
                'user_id' => $analysis->user_id, 'analysis_id' => $analysis->id,
                'feature' => $analysis->type, 'prompt_version' => $analysis->prompt_version,
                'provider' => config('ai.provider'), 'model' => config('ai.providers.openai.model'),
                'status' => 'running', 'created_at' => now(),
            ]);
        }, 3);
        if ($attempt === null) {
            return null;
        }
        $this->attempt = $attempt;
        $this->received = false;
        try {
            $advice = $request();
            if (! $this->hasReceived()) {
                $this->record($advice->provider, $advice->model, $advice->inputTokens, null, $advice->outputTokens);
            }
            DB::table('ai_usage_attempts')->where('id', $attempt)->update(['status' => 'completed', 'finished_at' => now()]);
            return $advice;
        } catch (\Throwable $exception) {
            DB::table('ai_usage_attempts')->where('id', $attempt)->update(['status' => 'failed', 'finished_at' => now()]);
            app(OperationalEvents::class)->record('ai', 'attempt_failed', $analysis->user_id);
            throw $exception;
        } finally {
            $this->attempt = null;
            $this->received = false;
            $this->month = null;
        }
    }

    private function hasReceived(): bool
    {
        return $this->received || (bool) DB::table('ai_usage_attempts')->where('id', $this->attempt)->value('response_received');
    }

    public function response(Response $response, string $model): void
    {
        if ($this->attempt === null) {
            return;
        }
        $integer = fn (mixed $value): ?int => is_int($value) && $value >= 0 ? $value : null;
        $this->record(
            'openai',
            (string) $response->json('model', $model),
            $integer($response->json('usage.input_tokens')),
            $integer($response->json('usage.input_tokens_details.cached_tokens')),
            $integer($response->json('usage.output_tokens'))
        );
    }

    private function record(string $provider, string $model, ?int $input, ?int $cached, ?int $output): void
    {
        if ($this->received) {
            return;
        }
        $this->received = true;
        $pricing = $this->pricing($provider, $model);
        $cost = null;
        if ($pricing !== null && $input !== null && $output !== null && ($cached !== null || $pricing['input'] === $pricing['cached_input'])) {
            $cached = min($input, max(0, $cached ?? 0));
            // A million USD micro-units per USD cancels the per-million-token divisor.
            $cost = (int) ceil(($input - $cached) * $pricing['input'] + $cached * $pricing['cached_input'] + $output * $pricing['output']);
        }
        DB::transaction(function () use ($provider, $model, $input, $cached, $output, $cost, $pricing): void {
            DB::table('operation_mutexes')->where('name', 'ai')->lockForUpdate()->first();
            // Anonymous monthly totals survive personal data deletion and retention.
            if ($cost !== null) {
                DB::table('ai_spend_months')->where('month', $this->month)->increment('cost_microusd', $cost);
                DB::table('ai_spend_months')->where('month', $this->month)->decrement('unknown_costs');
            }
            DB::table('ai_usage_attempts')->where('id', $this->attempt)->update([
                'provider' => $provider, 'model' => $model, 'input_tokens' => $input,
                'cached_input_tokens' => $cached, 'output_tokens' => $output,
                'cost_microusd' => $cost, 'pricing' => $pricing === null ? null : json_encode($pricing, JSON_THROW_ON_ERROR),
                'response_received' => true,
            ]);
        }, 3);
    }

    /** @return array{input: float, cached_input: float, output: float}|null */
    public function pricing(string $provider, string $model): ?array
    {
        if ($provider !== 'openai' || config('operations.ai.pricing_model') !== $model) {
            return null;
        }
        $rates = [];
        foreach (['input', 'cached_input', 'output'] as $key) {
            $rate = config('operations.ai.'.$key.'_usd_per_million');
            if (! is_numeric($rate) || ! is_finite((float) $rate) || (float) $rate < 0) {
                return null;
            }
            $rates[$key] = (float) $rate;
        }
        return $rates;
    }
}
