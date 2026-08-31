<?php

namespace App\Domain\Training\Services;

use App\Domain\Training\Enums\AiAnalysisStatus;
use App\Models\User;
use Carbon\CarbonImmutable;
use RuntimeException;

final class TrainingBlockContextBuilder
{
    public const SCHEMA_VERSION = 1;

    public function __construct(private readonly WeeklyTrainingContextBuilder $weeklyContextBuilder) {}

    /** @return array<string, mixed> */
    public function build(User $user, ?CarbonImmutable $blockStartsAt = null): array
    {
        $blockStartsAt ??= CarbonImmutable::today(config('app.timezone'))->addDay();
        $goalPlan = $user->aiAnalyses()
            ->where('type', 'goal_plan')
            ->where('status', AiAnalysisStatus::Completed)
            ->latest()
            ->first();

        if ($goalPlan === null || ! is_array($goalPlan->output)) {
            throw new RuntimeException('A completed goal plan is required for a training block.');
        }

        $baseContext = $this->weeklyContextBuilder->build($user, $blockStartsAt);

        return [
            'schema_version' => self::SCHEMA_VERSION,
            'purpose' => 'four_week_training_block',
            'generated_at' => CarbonImmutable::now()->toIso8601String(),
            'block_start' => $blockStartsAt->toDateString(),
            'block_end' => $blockStartsAt->addDays(27)->toDateString(),
            'block_weeks' => $this->weeks($blockStartsAt),
            'athlete_profile' => $baseContext['athlete_profile'],
            'goal_plan_analysis_id' => $goalPlan->id,
            'goal_plan' => $goalPlan->output,
            'recent_training' => $baseContext['recent_training'],
            'data_gaps' => $baseContext['data_gaps'],
            'privacy' => $baseContext['privacy'],
        ];
    }

    /** @return list<array{week: int, start: string, end: string}> */
    private function weeks(CarbonImmutable $blockStartsAt): array
    {
        return array_map(
            fn (int $index): array => [
                'week' => $index + 1,
                'start' => $blockStartsAt->addWeeks($index)->toDateString(),
                'end' => $blockStartsAt->addWeeks($index)->addDays(6)->toDateString(),
            ],
            range(0, 3),
        );
    }
}
