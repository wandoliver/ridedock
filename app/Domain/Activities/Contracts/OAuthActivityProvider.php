<?php

namespace App\Domain\Activities\Contracts;

use App\Domain\Activities\Data\ProviderToken;
use App\Models\ProviderConnection;

interface OAuthActivityProvider extends ActivityProvider
{
    public function authorizationUrl(string $state): string;

    public function exchangeAuthorizationCode(string $code): ProviderToken;

    public function refreshAccessToken(ProviderConnection $connection): ProviderToken;
}
