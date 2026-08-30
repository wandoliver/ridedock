<?php

namespace App\Domain\Activities\Services;

use App\Domain\Activities\Contracts\ActivityProvider;
use App\Domain\Activities\Enums\ProviderName;
use InvalidArgumentException;

final class ProviderRegistry
{
    /** @var array<string, ActivityProvider> */
    private array $providers = [];

    /** @param iterable<ActivityProvider> $providers */
    public function __construct(iterable $providers)
    {
        foreach ($providers as $provider) {
            $this->providers[$provider->key()->value] = $provider;
        }
    }

    public function get(ProviderName|string $provider): ActivityProvider
    {
        $key = $provider instanceof ProviderName ? $provider->value : $provider;

        return $this->providers[$key]
            ?? throw new InvalidArgumentException("Unknown activity provider [{$key}].");
    }

    /** @return array<string, ActivityProvider> */
    public function all(): array
    {
        return $this->providers;
    }
}
