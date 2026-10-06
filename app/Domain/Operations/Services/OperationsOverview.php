<?php

namespace App\Domain\Operations\Services;

use App\Domain\Training\Enums\AiAnalysisStatus;
use App\Models\AiAnalysis;
use App\Models\ProviderConnection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class OperationsOverview
{
    public function attempts(?int $userId = null): Builder
    {
        $query = DB::table('ai_usage_attempts')->where('created_at', '>=', now()->startOfMonth());
        if ($userId !== null) {
            $query->where('user_id', $userId);
        }
        return $query;
    }

    /** @return array<string, int|float|string|bool> */
    public function usage(?int $userId = null): array
    {
        $attempts = $this->attempts($userId);
        $requests = DB::table('ai_usage_requests')->where('created_at', '>=', now()->startOfMonth());
        if ($userId !== null) {
            $requests->where('user_id', $userId);
        }
        $cost = (int) (clone $attempts)->sum('cost_microusd');
        $unknown = (clone $attempts)->whereNull('cost_microusd')->count();
        if ($userId === null) {
            $total = DB::table('ai_spend_months')->where('month', now()->format('Y-m'))->first();
            $cost = (int) ($total->cost_microusd ?? 0);
            $unknown = (int) ($total->unknown_costs ?? 0);
        }
        $budget = (float) config('operations.ai.monthly_budget_usd');
        return [
            'month' => now()->format('Y-m'),
            'activities' => (clone $requests)->where('feature', 'activity')->count(),
            'plans' => (clone $requests)->where('feature', '!=', 'activity')->count(),
            'attempts' => (clone $attempts)->count(),
            'failed_attempts' => (clone $attempts)->where('status', 'failed')->count(),
            'input_tokens' => (int) (clone $attempts)->sum('input_tokens'),
            'output_tokens' => (int) (clone $attempts)->sum('output_tokens'),
            'cost_usd' => $cost / 1000000,
            'unknown_costs' => $unknown,
            'budget_usd' => $budget,
            'budget_warning' => $userId === null && $budget > 0 && $cost >= $budget * 1000000 * max(1, (int) config('operations.ai.budget_warning_percent')) / 100,
        ];
    }

    /** @return array<string, mixed> */
    public function snapshot(): array
    {
        $pending = AiAnalysis::query()->whereIn('status', [AiAnalysisStatus::Pending, AiAnalysisStatus::Processing]);
        $oldest = (clone $pending)->min('created_at');
        $finished = DB::table('ai_usage_attempts')->where('created_at', '>=', now()->subDay())->whereNotNull('finished_at')
            ->select(['created_at', 'finished_at'])->limit(10000)->get();
        $durations = $finished->map(fn ($row): int => max(0, \Carbon\CarbonImmutable::parse($row->finished_at)->getTimestamp() - \Carbon\CarbonImmutable::parse($row->created_at)->getTimestamp()));
        return [
            'usage' => $this->usage(),
            'pending' => $pending->count(),
            'oldest_pending_seconds' => $oldest ? max(0, now()->getTimestamp() - \Carbon\CarbonImmutable::parse($oldest)->getTimestamp()) : 0,
            'mean_attempt_seconds' => round((float) ($durations->avg() ?? 0), 1),
            'failed_last_day' => DB::table('ai_usage_attempts')->where('created_at', '>=', now()->subDay())->where('status', 'failed')->count(),
            'running' => DB::table('ai_usage_attempts')->where('status', 'running')->where('created_at', '>=', now()->subSeconds(max(180, (int) config('ai.providers.openai.timeout_seconds') + 60)))->count(),
            'provider_errors' => ProviderConnection::query()->where('status', 'error')->count(),
            'provider_waiting' => ProviderConnection::query()->where('status', 'waiting')->count(),
            'provider_stale' => ProviderConnection::query()->whereIn('status', ['syncing', 'queued'])->where('sync_started_at', '<', now()->subMinutes(config('operations.stale_minutes')))->count(),
            'pricing_configured' => app(AiUsage::class)->pricing('openai', (string) config('ai.providers.openai.model')) !== null,
        ];
    }
}
