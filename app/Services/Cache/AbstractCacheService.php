<?php

namespace App\Services\Cache;

use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Base cache service. Prefer dedicated subclasses over scattered Cache::remember().
 *
 * Never call Cache::flush() from application code — use targeted forget / forgetByPrefix.
 *
 * Stale-while-revalidate (SWR) for mostly-static data (curriculum) is prepared here
 * and fully wired in Phase 4 when curriculum endpoints exist.
 */
abstract class AbstractCacheService
{
    protected function store(): Repository
    {
        return Cache::store();
    }

    /**
     * @template T
     * @param  Closure(): T  $callback
     * @return T
     */
    public function remember(string $key, Closure $callback, ?int $ttlSeconds = null): mixed
    {
        $ttl = $ttlSeconds ?? $this->defaultTtl();

        return $this->store()->remember($key, $ttl, $callback);
    }

    /**
     * Stampede-safe remember: only one process regenerates; others wait briefly.
     *
     * @template T
     * @param  Closure(): T  $callback
     * @return T
     */
    public function rememberWithLock(string $key, Closure $callback, ?int $ttlSeconds = null, int $lockSeconds = 10): mixed
    {
        $ttl = $ttlSeconds ?? $this->defaultTtl();
        $cached = $this->store()->get($key);

        if ($cached !== null) {
            return $cached;
        }

        $lock = $this->store()->lock("lock:{$key}", $lockSeconds);

        try {
            $lock->block(5);

            $cached = $this->store()->get($key);

            if ($cached !== null) {
                return $cached;
            }

            $value = $callback();
            $this->store()->put($key, $value, $ttl);

            return $value;
        } catch (LockTimeoutException) {
            Log::warning('Cache lock timeout; falling back to direct generation.', [
                'key' => $key,
                'service' => static::class,
            ]);

            return $callback();
        } finally {
            $lock->release();
        }
    }

    /**
     * Lightweight SWR skeleton: serve fresh cache when present; otherwise regenerate.
     * Phase 4 may extend this with soft TTL + background refresh for curriculum.
     *
     * @template T
     * @param  Closure(): T  $callback
     * @return T
     */
    public function rememberStaleWhileRevalidate(string $key, Closure $callback, ?int $ttlSeconds = null): mixed
    {
        return $this->rememberWithLock($key, $callback, $ttlSeconds);
    }

    public function put(string $key, mixed $value, ?int $ttlSeconds = null): void
    {
        $this->store()->put($key, $value, $ttlSeconds ?? $this->defaultTtl());
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->store()->get($key, $default);
    }

    public function forget(string $key): bool
    {
        return $this->store()->forget($key);
    }

    /**
     * Forget keys matching a prefix. Uses Redis SCAN when available; otherwise
     * callers should prefer explicit key lists / cache tags.
     */
    public function forgetByPrefix(string $prefix): void
    {
        $store = $this->store();
        $storeName = config('cache.default');

        if ($storeName === 'redis' && method_exists($store->getStore(), 'connection')) {
            /** @var \Illuminate\Redis\Connections\Connection $connection */
            $connection = $store->getStore()->connection();
            $redisPrefix = (string) config('cache.prefix');
            $pattern = $redisPrefix.$prefix.'*';

            $cursor = '0';
            do {
                [$cursor, $keys] = $connection->scan($cursor, ['match' => $pattern, 'count' => 100]);

                if (! empty($keys)) {
                    $connection->del(...$keys);
                }
            } while ($cursor !== '0' && $cursor !== 0);

            return;
        }

        Log::debug('forgetByPrefix skipped for non-Redis store; forget keys explicitly.', [
            'prefix' => $prefix,
            'store' => $storeName,
        ]);
    }

    protected function defaultTtl(): int
    {
        return 3600;
    }
}
