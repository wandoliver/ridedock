<?php

namespace App\Domain\Activities\Contracts;

use App\Domain\Activities\Data\ProviderActivity;
use App\Domain\Activities\Data\ProviderPage;
use App\Domain\Activities\Enums\ProviderName;
use App\Models\ProviderConnection;
use Carbon\CarbonImmutable;

interface ActivityProvider
{
    public function key(): ProviderName;

    /** @return list<string> */
    public function capabilities(): array;

    public function activities(
        ProviderConnection $connection,
        ?CarbonImmutable $since = null,
        ?string $cursor = null,
    ): ProviderPage;

    public function activity(ProviderConnection $connection, string $externalId): ProviderActivity;
}
