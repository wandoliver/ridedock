<?php

namespace App\Domain\Training\Services;

use App\Domain\Training\Enums\AiAnalysisStatus;
use App\Models\User;
use Carbon\CarbonImmutable;
use Throwable;

final class NextPlannedSessionResolver
{
    /**
     * @return array{date: string, title: string, type: string, duration_minutes: int|null, intensity: string, details: string, rationale: string}|null
     */
    public function resolve(User $user, ?CarbonImmutable $today = null): ?array
    {
        $today ??= CarbonImmutable::today(config('app.timezone'));
        $plan = $user->aiAnalyses()
            ->where('type', 'weekly_plan')
            ->where('status', AiAnalysisStatus::Completed)
            ->latest()
            ->first();
        $days = $plan?->output['days'] ?? null;

        if (! is_array($days)) {
            return null;
        }

        foreach ($days as $day) {
            if (! is_array($day) || ! is_string($day['date'] ?? null) || ($day['completed'] ?? false)) {
                continue;
            }

            try {
                $date = CarbonImmutable::createFromFormat('!Y-m-d', $day['date']);
            } catch (Throwable) {
                continue;
            }

            if ($date === null || $date->isBefore($today)) {
                continue;
            }

            if (! is_string($day['title'] ?? null)
                || ! is_string($day['type'] ?? null)
                || ! is_string($day['intensity'] ?? null)
                || ! is_string($day['details'] ?? null)
                || ! is_string($day['rationale'] ?? null)
                || (! is_int($day['duration_minutes'] ?? null) && ($day['duration_minutes'] ?? null) !== null)) {
                continue;
            }

            return [
                'date' => $day['date'],
                'title' => $day['title'],
                'type' => $day['type'],
                'duration_minutes' => $day['duration_minutes'] ?? null,
                'intensity' => $day['intensity'],
                'details' => $day['details'],
                'rationale' => $day['rationale'],
            ];
        }

        return null;
    }
}
