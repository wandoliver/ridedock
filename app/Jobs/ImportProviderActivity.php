<?php

namespace App\Jobs;

use App\Domain\Activities\Contracts\ActivityFileProvider;
use App\Domain\Activities\Contracts\ActivitySourceEnricher;
use App\Domain\Activities\Services\ActivityFileArchiver;
use App\Domain\Activities\Services\ActivityImporter;
use App\Domain\Activities\Services\ProviderRegistry;
use App\Domain\Activities\Services\ProviderTokenManager;
use App\Models\ProviderConnection;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ImportProviderActivity implements ShouldQueue
{
    use Queueable;

    public bool $deleteWhenMissingModels = true;

    public int $tries = 0;
    public int $maxExceptions = 3;
    public int $timeout = 300;
    public int $deadline = 0;
    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    public function __construct(
        public readonly ProviderConnection $providerConnection,
        public readonly string $externalId,
    ) {
        $this->deadline = now()->addDays(2)->getTimestamp();
    }

    public function retryUntil(): \DateTimeInterface
    {
        return $this->deadline > 0 ? \Carbon\CarbonImmutable::createFromTimestamp($this->deadline) : \Carbon\CarbonImmutable::now()->addDays(2);
    }

    public function handle(
        ProviderRegistry $providers,
        ProviderTokenManager $tokens,
        ActivityImporter $importer,
        ActivityFileArchiver $files,
        ActivitySourceEnricher $enricher,
    ): void {
        if (! $this->providerConnection->stillConnected()) {
            return;
        }

        $lock = app(\App\Domain\Operations\Services\ProviderOperations::class)->lock('provider-sync:'.$this->providerConnection->id, (int) config('operations.lock_seconds'));
        try {
            if (! $lock->get()) {
                throw new \App\Domain\Operations\Exceptions\WorkDeferred(5);
            }
            $connection = $tokens->ensureFresh($this->providerConnection);
            $provider = $providers->get($connection->provider);
            $payload = $provider->activity($connection, $this->externalId);
            if (! $connection->stillConnected()) {
                return;
            }
            $source = $importer->import($connection, $payload);
            if ($source === null) {
                return;
            }

            if (config('activity-providers.sync.store_source_files')
                && $provider instanceof ActivityFileProvider) {
                if (! $source->source_file_path) {
                    $files->archive($provider, $connection, $source);
                }

                if ($source->source_file_path) {
                    $enricher->enrich($source->refresh());
                }
            }
        } catch (\App\Domain\Operations\Exceptions\WorkDeferred $deferred) {
            if ($this->job === null || $this->job instanceof \Illuminate\Queue\Jobs\SyncJob) {
                throw $deferred;
            }
            $this->release($deferred->retryAfter);
        } catch (\Throwable) {
            app(\App\Domain\Operations\Services\OperationalEvents::class)->record('provider', 'import_failed', $this->providerConnection->user_id, $this->providerConnection->provider->value);
            throw new \RuntimeException("Provider activity import failed. Retry the import.");
        } finally {
            $lock->release();
        }
    }

    public function failed(?\Throwable $exception): void
    {
        app(\App\Domain\Operations\Services\OperationalEvents::class)->record('provider', 'import_job_failed', $this->providerConnection->user_id, $this->providerConnection->provider->value);
    }
}
