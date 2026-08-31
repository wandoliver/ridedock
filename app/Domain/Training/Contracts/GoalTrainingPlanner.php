<?php

namespace App\Domain\Training\Contracts;

use App\Domain\Training\Data\TrainingAdvice;

interface GoalTrainingPlanner
{
    public function isConfigured(): bool;

    /** @param array<string, mixed> $context */
    public function generateGoalPlan(array $context): TrainingAdvice;
}
