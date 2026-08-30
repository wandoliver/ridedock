<?php

namespace App\Domain\Activities\Contracts;

use App\Models\ActivitySource;

interface ActivitySourceEnricher
{
    /**
     * Enrich normalized metrics, streams and events from an archived source file.
     * Implementations must be idempotent and must never expose the private file path.
     */
    public function enrich(ActivitySource $source): void;
}
