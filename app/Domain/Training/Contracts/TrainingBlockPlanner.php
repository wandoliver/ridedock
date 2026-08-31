<?php

namespace App\Domain\Training\Contracts;

use App\Domain\Training\Data\TrainingAdvice;

interface TrainingBlockPlanner
{
    public function isConfigured(): bool;

    /** @param array<string, mixed> $context */
    public function generateTrainingBlock(array $context): TrainingAdvice;
}
