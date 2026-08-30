<?php

namespace App\Domain\Activities\Services;

use App\Domain\Activities\Contracts\OAuthActivityProvider;
use App\Domain\Activities\Data\ProviderToken;
use App\Domain\Activities\Enums\ConnectionStatus;
use App\Models\ProviderConnection;
use RuntimeException;

final class ProviderTokenManager
{
    public function __construct(private readonly ProviderRegistry $providers) {}

    public function ensureFresh(ProviderConnection $connection): ProviderConnection
    {
        if ($connection->token_expires_at === null || $connection->token_expires_at->isAfter(now()->addMinute())) {
            return $connection;
        }

        $provider = $this->providers->get($connection->provider);

        if (! $provider instanceof OAuthActivityProvider || ! $connection->refresh_token) {
            throw new RuntimeException('The provider token has expired and cannot be refreshed.');
        }

        $this->persist($connection, $provider->refreshAccessToken($connection));

        return $connection->refresh();
    }

    public function persist(ProviderConnection $connection, ProviderToken $token): void
    {
        $connection->forceFill([
            'access_token' => $token->accessToken,
            'refresh_token' => $token->refreshToken ?: $connection->refresh_token,
            'token_expires_at' => $token->expiresAt,
            'external_user_id' => $token->externalUserId ?: $connection->external_user_id,
            'scopes' => $token->scopes ?: $connection->scopes,
            'status' => ConnectionStatus::Active,
            'last_error' => null,
        ])->save();
    }
}
