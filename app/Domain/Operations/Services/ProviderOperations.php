<?php

namespace App\Domain\Operations\Services;

use App\Domain\Operations\Exceptions\WorkDeferred;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

final class ProviderOperations
{
    public function cache(): Repository
    {
        return Cache::store(config('operations.cache_store'));
    }

    public function lock(string $key, int $seconds): Lock
    {
        $store = $this->cache()->getStore();
        if (! $store instanceof LockProvider) {
            throw new RuntimeException('Provider operations require a lock-capable cache.');
        }
        return $store->lock($key, $seconds);
    }

    public function assertAllowed(string $provider): void
    {
        if (! config('operations.hosted')) {
            return;
        }
        $store = config('operations.cache_store') ?? config('cache.default');
        if (! in_array(config("cache.stores.$store.driver"), ['database', 'redis', 'memcached'], true)) {
            throw new RuntimeException('Hosted provider operations require a shared lock-capable cache.');
        }
        if (! config("activity-providers.$provider.hosted_approved") || ! config("activity-providers.$provider.terms_reviewed_at")) {
            throw new RuntimeException('Provider approval and terms review are required for hosted use.');
        }
    }

    public function client(string $provider, PendingRequest $client, bool $metered = true): PendingRequest
    {
        return $client->beforeSending(function () use ($provider, $metered): void {
            $this->assertAllowed($provider);
            $this->reserve($provider, $metered);
        })->afterResponse(function (Response $response) use ($provider): ?Response {
            $this->observe($provider, $response);
            return null;
        });
    }

    public function reserve(string $provider, bool $metered = true): void
    {
        $key = $this->key($provider);
        $cache = $this->cache();
        $lock = $this->lock($key.':lock', 10);
        if (! $lock->get()) {
            throw new WorkDeferred(2);
        }
        try {
            $state = $cache->get($key, []);
            $time = now()->getTimestamp();
            if (($state['until'] ?? 0) > $time) {
                throw new WorkDeferred($state['until'] - $time);
            }
            if (! $metered) {
                return;
            }
            $wait = 0;
            foreach (config("activity-providers.$provider.rate_limits", []) as $seconds => $limit) {
                if ($limit < 1) {
                    continue;
                }
                $seconds = (int) $seconds;
                $bucket = intdiv($time, $seconds);
                $window = $state['windows'][$seconds] ?? ['bucket' => $bucket, 'count' => 0];
                if ($window['bucket'] !== $bucket) {
                    $window = ['bucket' => $bucket, 'count' => 0];
                }
                if ($window['count'] >= $limit) {
                    $wait = max($wait, ($bucket + 1) * $seconds - $time);
                }
                $state['windows'][$seconds] = $window;
            }
            if ($wait > 0) {
                throw new WorkDeferred($wait);
            }
            foreach ($state['windows'] ?? [] as $seconds => $window) {
                $state['windows'][$seconds]['count']++;
            }
            $cache->put($key, $state, 172800);
        } finally {
            $lock->release();
        }
    }

    public function observe(string $provider, Response $response): void
    {
        $limited = $response->status() === 429;
        $remaining = array_map('trim', explode(',', (string) $response->header('X-RateLimit-Remaining')));
        $exhausted = $provider === 'wahoo' && in_array('0', $remaining, true);
        if (! $limited && ! $exhausted && $response->status() < 500) {
            return;
        }
        $delay = 60;
        $retry = $response->header('Retry-After');
        if (ctype_digit($retry)) {
            $delay = max(1, (int) $retry);
        } elseif ($retry !== '') {
            try {
                $delay = max(1, CarbonImmutable::parse($retry)->getTimestamp() - now()->getTimestamp());
            } catch (\Throwable) {
                // Malformed upstream headers use the conservative fallback.
            }
        }
        if ($provider === 'wahoo' && ctype_digit((string) $response->header('X-RateLimit-Reset'))) {
            $delay = max($delay, (int) $response->header('X-RateLimit-Reset'));
        }
        $key = $this->key($provider);
        $cache = $this->cache();
        $this->lock($key.':lock', 10)->block(3, function () use ($cache, $key, $delay): void {
            $state = $cache->get($key, []);
            $state['until'] = max($state['until'] ?? 0, now()->getTimestamp() + $delay);
            $cache->put($key, $state, max(172800, $delay + 60));
        });
        if ($limited || $response->status() >= 500) {
            throw new WorkDeferred($delay);
        }
    }

    public function cooldown(string $provider): int
    {
        return max(0, (int) ($this->cache()->get($this->key($provider), [])['until'] ?? 0) - now()->getTimestamp());
    }

    private function key(string $provider): string
    {
        return 'provider-budget:'.$provider.':'.hash('sha256', (string) config("activity-providers.$provider.client_id"));
    }
}
