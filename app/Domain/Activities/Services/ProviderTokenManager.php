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
        $lock = app(\App\Domain\Operations\Services\ProviderOperations::class)->lock('provider-token:'.$connection->id, 120);
        if (! $lock->get()) {
            throw new \App\Domain\Operations\Exceptions\WorkDeferred(5);
        }
        try {
            // A concurrent refresh may have rotated both tokens. Re-read under the shared lock.
            $current = ProviderConnection::query()->findOrFail($connection->id);
            return $this->refreshIfExpired($current);
        } finally {
            $lock->release();
        }
    }

    private function refreshIfExpired(ProviderConnection $connection): ProviderConnection
    {
        if (! $connection->stillConnected()) {
            throw new RuntimeException('The provider connection is disconnected.');
        }

        if ($connection->token_expires_at === null || $connection->token_expires_at->isAfter(now()->addMinute())) {
            return $connection;
        }

        $provider = $this->providers->get($connection->provider);

        if (! $provider instanceof OAuthActivityProvider || ! $connection->refresh_token) {
            throw new RuntimeException('The provider token has expired and cannot be refreshed.');
        }

        $this->persist($connection, $provider->refreshAccessToken($connection));

        if (! $connection->stillConnected()) {
            throw new RuntimeException('The provider connection is disconnected.');
        }

        return $connection->refresh();
    }

    public function persist(ProviderConnection $connection, ProviderToken $token): void
    {
        $connection->updateWhileConnected([
            'access_token' => $token->accessToken,
            'refresh_token' => $token->refreshToken ?: $connection->refresh_token,
            'token_expires_at' => $token->expiresAt,
            'external_user_id' => $token->externalUserId ?: $connection->external_user_id,
            'scopes' => $token->scopes ?: $connection->scopes,
            'status' => ConnectionStatus::Active,
            'last_error' => null,
        ]);
    }
}
