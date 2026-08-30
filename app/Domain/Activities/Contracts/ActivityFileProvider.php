<?php

namespace App\Domain\Activities\Contracts;

use App\Models\ProviderConnection;

interface ActivityFileProvider extends ActivityProvider
{
    public function downloadActivityFile(ProviderConnection $connection, string $externalId): string;

    public function activityFileExtension(): string;
}
