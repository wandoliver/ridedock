<?php

namespace App\Domain\Activities\Services;

use App\Domain\Activities\Contracts\ActivityFileProvider;
use App\Domain\Activities\Enums\ConnectionStatus;
use App\Jobs\EnrichActivitySource;
use App\Models\ProviderConnection;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Throwable;

final class SyncProviderActivities
{
    public function __construct(
        private readonly ProviderRegistry $providers,
        private readonly ProviderTokenManager $tokens,
        private readonly ActivityImporter $importer,
        private readonly ActivityFileArchiver $files,
    ) {}

    public function handle(ProviderConnection $connection): int
    {
        if (! $connection->stillConnected()) {
            return 0;
        }

        $connection = $this->tokens->ensureFresh($connection);
        $provider = $this->providers->get($connection->provider);
        $cursor = $connection->sync_cursor;
        $since = $cursor === null && $connection->last_synced_at
            ? CarbonImmutable::instance($connection->last_synced_at)
                ->subDays(config('activity-providers.sync.overlap_days'))
            : null;
        $imported = 0;
        $pageCount = 0;

        $connection->updateWhileConnected(['status' => ConnectionStatus::Syncing, 'last_error' => null]);

        try {
            do {
                if (! $connection->stillConnected()) {
                    return $imported;
                }
                $page = $provider->activities($connection, $since, $cursor);

                foreach ($page->activities as $payload) {
                    if (! $connection->stillConnected()) {
                        return $imported;
                    }
                    $source = $this->importer->import($connection, $payload);
                    $imported++;

                    if (config('activity-providers.sync.store_source_files')
                        && $provider instanceof ActivityFileProvider) {
                        if (! $source->source_file_path) {
                            $this->files->archive($provider, $connection, $source);
                        }

                        if (! Arr::has($source->metadata ?? [], 'fit_parser')) {
                            EnrichActivitySource::dispatch($source->refresh());
                        }
                    }
                }

                $cursor = $page->nextCursor;
                $connection->updateWhileConnected(['sync_cursor' => $cursor]);
                $pageCount++;
            } while ($cursor !== null && $pageCount < config('activity-providers.sync.max_pages'));

            if ($cursor !== null) {
                throw new \RuntimeException('Sync page limit reached; resume the connection sync to continue.');
            }

            $connection->updateWhileConnected([
                'status' => ConnectionStatus::Active,
                'sync_cursor' => null,
                'last_synced_at' => now(),
                'last_error' => null,
            ]);

            return $imported;
        } catch (Throwable $exception) {
            $connection->updateWhileConnected([
                'status' => ConnectionStatus::Error,
                'last_error' => mb_substr($exception->getMessage(), 0, 2000),
            ]);

            throw $exception;
        }
    }
}
