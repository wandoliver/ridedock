<?php

namespace App\Domain\Training\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use RuntimeException;

final class GoalTrainingContextBuilder
{
    public const SCHEMA_VERSION = 2;

    public function __construct(private readonly WeeklyTrainingContextBuilder $weeklyContextBuilder) {}

    /** @return array<string, mixed> */
    public function build(User $user, ?CarbonImmutable $planStartsAt = null): array
    {
        $user->loadMissing('athleteProfile');
        $planStartsAt ??= CarbonImmutable::today(config('app.timezone'))->addDay();
        $goalDate = $user->athleteProfile?->goal_date;

        if ($goalDate === null || $goalDate->isBefore($planStartsAt)) {
            throw new RuntimeException('A future goal date is required for a goal plan.');
        }

        $baseContext = $this->weeklyContextBuilder->build($user, $planStartsAt);

        return [
            'schema_version' => self::SCHEMA_VERSION,
            'purpose' => 'goal_training_plan',
            'generated_at' => CarbonImmutable::now()->toIso8601String(),
            'plan_start' => $planStartsAt->toDateString(),
            'plan_end' => $goalDate->toDateString(),
            'planning_days' => (int) $planStartsAt->diffInDays($goalDate) + 1,
            'athlete_profile' => $baseContext['athlete_profile'],
            'recent_training' => $baseContext['recent_training'],
            'data_gaps' => $baseContext['data_gaps'],
            'privacy' => $baseContext['privacy'],
        ];
    }
}
