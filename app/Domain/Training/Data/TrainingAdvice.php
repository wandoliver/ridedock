<?php

namespace App\Domain\Training\Data;

final readonly class TrainingAdvice
{
    /** @param array<string, mixed> $output */
    public function __construct(
        public array $output,
        public string $provider,
        public string $model,
        public string $responseId,
        public ?int $inputTokens,
        public ?int $outputTokens,
    ) {}
}
