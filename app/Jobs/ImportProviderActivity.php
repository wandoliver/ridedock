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

    public function __construct(
        public readonly ProviderConnection $providerConnection,
        public readonly string $externalId,
    ) {}

    public function handle(
        ProviderRegistry $providers,
        ProviderTokenManager $tokens,
        ActivityImporter $importer,
        ActivityFileArchiver $files,
        ActivitySourceEnricher $enricher,
    ): void {
        $connection = $tokens->ensureFresh($this->providerConnection);
        $provider = $providers->get($connection->provider);
        $payload = $provider->activity($connection, $this->externalId);
        $source = $importer->import($connection, $payload);

        if (config('activity-providers.sync.store_source_files')
            && $provider instanceof ActivityFileProvider) {
            if (! $source->source_file_path) {
                $files->archive($provider, $connection, $source);
            }

            $enricher->enrich($source->fresh());
        }
    }
}
