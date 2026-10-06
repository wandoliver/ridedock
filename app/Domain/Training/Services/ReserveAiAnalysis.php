<?php

namespace App\Domain\Training\Services;

use App\Domain\Training\Enums\AiAnalysisStatus;
use App\Models\AiAnalysis;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReserveAiAnalysis
{
    /** @param array<string, mixed> $attributes */
    public function reserve(User $user, array $attributes): AiAnalysis
    {
        return DB::transaction(function () use ($user, $attributes): AiAnalysis {
            DB::table('operation_mutexes')->where('name', 'ai')->lockForUpdate()->first();
            // Serialize reservations for an owner across concurrent requests.
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $existing = $user->aiAnalyses()
                ->where('type', $attributes['type'])
                ->where('activity_id', $attributes['activity_id'] ?? null)
                ->whereIn('status', [AiAnalysisStatus::Pending, AiAnalysisStatus::Processing])
                ->first();

            if ($existing) {
                return $existing;
            }
            $activity = $attributes['type'] === 'activity';
            $quota = $locked->aiQuota($activity);
            $usage = DB::table('ai_usage_requests')->where('user_id', $user->id)->where('created_at', '>=', now()->startOfMonth());
            $activity ? $usage->where('feature', 'activity') : $usage->where('feature', '!=', 'activity');
            if ($usage->count() >= $quota) {
                throw \Illuminate\Validation\ValidationException::withMessages(['ai' => __('operations.quota_exhausted')]);
            }
            $pending = AiAnalysis::query()->whereIn('status', [AiAnalysisStatus::Pending, AiAnalysisStatus::Processing]);
            if ((clone $pending)->where('user_id', $user->id)->count() >= max(1, (int) config('operations.ai.user_pending_limit'))
                || $pending->count() >= max(1, (int) config('operations.ai.pending_limit'))) {
                throw \Illuminate\Validation\ValidationException::withMessages(['ai' => __('operations.capacity_full')]);
            }
            $analysis = $user->aiAnalyses()->create($attributes);
            DB::table('ai_usage_requests')->insert([
                'user_id' => $user->id, 'analysis_id' => $analysis->id,
                'feature' => $analysis->type, 'created_at' => now(),
            ]);
            return $analysis;
        }, 3);
    }
}
