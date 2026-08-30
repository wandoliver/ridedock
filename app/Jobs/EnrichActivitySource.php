<?php

namespace App\Jobs;

use App\Domain\Activities\Contracts\ActivitySourceEnricher;
use App\Models\ActivitySource;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class EnrichActivitySource implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $uniqueFor = 3600;

    public int $timeout = 60;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public function __construct(public readonly ActivitySource $activitySource) {}

    public function handle(ActivitySourceEnricher $enricher): void
    {
        $enricher->enrich($this->activitySource->refresh());
    }

    public function uniqueId(): string
    {
        return (string) $this->activitySource->id;
    }
}
