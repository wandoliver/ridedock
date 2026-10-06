<?php

namespace App\Jobs;

use App\Domain\Activities\Services\SyncProviderActivities;
use App\Models\ProviderConnection;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncProviderConnection implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public bool $deleteWhenMissingModels = true;

    public int $tries = 0;

    public int $maxExceptions = 3;

    public int $deadline = 0;

    public int $uniqueFor = 3600;

    public int $timeout = 300;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    public function __construct(public readonly ProviderConnection $providerConnection)
    {
        $this->deadline = now()->addDays(2)->getTimestamp();
    }

    public function retryUntil(): \DateTimeInterface
    {
        return $this->deadline > 0 ? \Carbon\CarbonImmutable::createFromTimestamp($this->deadline) : \Carbon\CarbonImmutable::now()->addDays(2);
    }

    public function handle(SyncProviderActivities $sync): void
    {
        try {
            $sync->handle($this->providerConnection);
        } catch (\App\Domain\Operations\Exceptions\WorkDeferred $deferred) {
            if ($this->job === null || $this->job instanceof \Illuminate\Queue\Jobs\SyncJob) {
                throw $deferred;
            }
            $this->release($deferred->retryAfter);
        }
    }

    public function failed(?\Throwable $exception): void
    {
        $this->providerConnection->updateWhileConnected([
            'status' => \App\Domain\Activities\Enums\ConnectionStatus::Error,
            'last_error' => 'Provider synchronization failed. Retry the synchronization to resume.',
            'sync_retry_at' => null,
        ]);
        app(\App\Domain\Operations\Services\OperationalEvents::class)->record('provider', 'sync_job_failed', $this->providerConnection->user_id, $this->providerConnection->provider->value);
    }

    public function uniqueId(): string
    {
        return (string) $this->providerConnection->id;
    }
}
