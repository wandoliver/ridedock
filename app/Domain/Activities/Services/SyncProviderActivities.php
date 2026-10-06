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
        $operations = app(\App\Domain\Operations\Services\ProviderOperations::class);
        $lock = $operations->lock('provider-sync:'.$connection->id, (int) config('operations.lock_seconds'));
        if (! $lock->get()) {
            throw new \App\Domain\Operations\Exceptions\WorkDeferred(5);
        }
        try {
            return $this->synchronize($connection->refresh());
        } finally {
            $lock->release();
        }
    }

    private function synchronize(ProviderConnection $connection): int
    {
        if (! $connection->stillConnected()) {
            return 0;
        }

        try {
            $started = microtime(true);
            app(\App\Domain\Operations\Services\ProviderOperations::class)->assertAllowed($connection->provider->value);
            $connection = $this->tokens->ensureFresh($connection);
            $provider = $this->providers->get($connection->provider);
            $cursor = $connection->sync_cursor;
            $since = $connection->sync_since ?? ($cursor === null && $connection->last_synced_at
                ? CarbonImmutable::instance($connection->last_synced_at)
                    ->subDays(config('activity-providers.sync.overlap_days'))
                : null);
            $imported = 0;
            $pageCount = 0;

            $connection->updateWhileConnected(['status' => ConnectionStatus::Syncing, 'last_error' => null, 'sync_started_at' => now(), 'sync_retry_at' => null, 'sync_since' => $since]);

            do {
                if (! $connection->stillConnected()) {
                    return $imported;
                }
                $page = $provider->activities($connection, $since, $cursor);

                foreach ($page->activities as $payload) {
                    if (microtime(true) - $started >= config('operations.sync_slice_seconds')) {
                        throw new \App\Domain\Operations\Exceptions\WorkDeferred(1);
                    }
                    if (! $connection->stillConnected()) {
                        return $imported;
                    }
                    $source = $this->importer->import($connection, $payload);
                    if ($source === null) {
                        continue;
                    }
                    $imported++;

                    if (config('activity-providers.sync.store_source_files')
                        && $provider instanceof ActivityFileProvider) {
                        if (! $source->source_file_path) {
                            $this->files->archive($provider, $connection, $source);
                        }

                        if ($source->source_file_path && ! Arr::has($source->metadata ?? [], 'fit_parser')) {
                            EnrichActivitySource::dispatch($source->refresh());
                        }
                    }
                }

                $cursor = $page->nextCursor;
                $connection->updateWhileConnected(['sync_cursor' => $cursor]);
                $pageCount++;
                if ($cursor !== null && microtime(true) - $started >= config('operations.sync_slice_seconds')) {
                    throw new \App\Domain\Operations\Exceptions\WorkDeferred(1);
                }
            } while ($cursor !== null && $pageCount < config('activity-providers.sync.max_pages'));

            if ($cursor !== null) {
                throw new \App\Domain\Operations\Exceptions\WorkDeferred(1);
            }

            $connection->updateWhileConnected([
                'status' => ConnectionStatus::Active,
                'sync_cursor' => null,
                'last_synced_at' => now(),
                'last_error' => null,
                'sync_retry_at' => null,
                'sync_since' => null,
            ]);

            return $imported;
        } catch (\App\Domain\Operations\Exceptions\WorkDeferred $deferred) {
            $connection->updateWhileConnected(['status' => ConnectionStatus::Waiting, 'sync_retry_at' => now()->addSeconds($deferred->retryAfter)]);
            throw $deferred;
        } catch (Throwable) {
            app(\App\Domain\Operations\Services\OperationalEvents::class)->record('provider', 'sync_failed', $connection->user_id, $connection->provider->value);
            $message = 'Provider synchronization failed. Retry the synchronization to resume.';
            $connection->updateWhileConnected([
                'status' => ConnectionStatus::Error,
                'last_error' => $message,
            ]);

            throw new \RuntimeException($message);
        }
    }
}
