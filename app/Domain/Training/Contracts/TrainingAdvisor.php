<?php

namespace App\Domain\Training\Contracts;

use App\Domain\Training\Data\TrainingAdvice;

interface TrainingAdvisor
{
    public function isConfigured(): bool;

    /** @param array<string, mixed> $context */
    public function analyzeActivity(array $context): TrainingAdvice;
}
