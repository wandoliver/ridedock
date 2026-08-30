<?php

namespace App\Domain\Training\Contracts;

use App\Domain\Training\Data\TrainingAdvice;

interface WeeklyTrainingPlanner
{
    public function isConfigured(): bool;

    /** @param array<string, mixed> $context */
    public function generateWeeklyPlan(array $context): TrainingAdvice;
}
