<?php

namespace App\Domain\Activities\Data;

final readonly class ProviderPage
{
    /** @param list<ProviderActivity> $activities */
    public function __construct(
        public array $activities,
        public ?string $nextCursor,
    ) {}
}
