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

    public int $tries = 3;

    public int $uniqueFor = 3600;

    public int $timeout = 300;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    public function __construct(public readonly ProviderConnection $providerConnection) {}

    public function handle(SyncProviderActivities $sync): void
    {
        $sync->handle($this->providerConnection);
    }

    public function uniqueId(): string
    {
        return (string) $this->providerConnection->id;
    }
}
