<?php

namespace App\Domain\Activities\Contracts;

use App\Models\ProviderConnection;

interface RevocableActivityProvider
{
    public function revokeAccess(ProviderConnection $connection): void;
}
