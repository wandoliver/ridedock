<?php

namespace App\Domain\Activities\Data;

use Carbon\CarbonImmutable;

final readonly class ProviderToken
{
    /** @param list<string> $scopes */
    public function __construct(
        public string $accessToken,
        public ?string $refreshToken,
        public ?CarbonImmutable $expiresAt,
        public ?string $externalUserId,
        public array $scopes = [],
    ) {}
}
