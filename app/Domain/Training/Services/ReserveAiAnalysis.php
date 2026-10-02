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
            // Serialize reservations for an owner across concurrent requests.
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $existing = $user->aiAnalyses()
                ->where('type', $attributes['type'])
                ->where('activity_id', $attributes['activity_id'] ?? null)
                ->whereIn('status', [AiAnalysisStatus::Pending, AiAnalysisStatus::Processing])
                ->first();

            return $existing ?? $user->aiAnalyses()->create($attributes);
        }, 3);
    }
}
