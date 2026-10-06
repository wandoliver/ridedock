<?php

namespace App\Domain\Activities\Services;

use App\Domain\Activities\Contracts\RevocableActivityProvider;
use App\Domain\Activities\Enums\ConnectionStatus;
use App\Models\ProviderConnection;
use Throwable;

final class DisconnectProviderConnection
{
    public function __construct(private readonly ProviderRegistry $providers) {}

    public function handle(ProviderConnection $connection): bool
    {
        $credentials = clone $connection;
        // Clear locally first, even if the provider is unavailable. Keep imported sources.
        $connection->forceFill([
            'access_token' => null,
            'refresh_token' => null,
            'token_expires_at' => null,
            'external_user_id' => null,
            'scopes' => null,
            'capabilities' => null,
            'metadata' => null,
            'sync_cursor' => null,
            'sync_started_at' => null,
            'sync_retry_at' => null,
            'sync_since' => null,
            'last_error' => null,
            'status' => ConnectionStatus::Revoked,
        ])->save();

        if (! $credentials->access_token) {
            return true;
        }

        try {
            $provider = $this->providers->get($credentials->provider);
            if ($provider instanceof RevocableActivityProvider) {
                $provider->revokeAccess($credentials);
            }
        } catch (Throwable) {
            // Provider exceptions can contain credentials; do not persist or log them.
            return false;
        }

        return true;
    }
}
