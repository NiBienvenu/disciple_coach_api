<?php

namespace App\Services\Cache;

use Closure;

/**
 * Per-user cache. Every key includes the user id.
 *
 * Never use global keys for favorites, progress, or dashboard data.
 */
class UserCacheService extends AbstractCacheService
{
    /**
     * @template T
     * @param  Closure(): T  $callback
     * @return T
     */
    public function rememberProfile(int|string $userId, Closure $callback, ?int $ttlSeconds = null): mixed
    {
        return $this->remember(CacheKey::userProfile($userId), $callback, $ttlSeconds);
    }

    /**
     * @template T
     * @param  Closure(): T  $callback
     * @return T
     */
    public function rememberProgress(int|string $userId, Closure $callback, ?int $ttlSeconds = null): mixed
    {
        return $this->remember(CacheKey::userProgress($userId), $callback, $ttlSeconds);
    }

    /**
     * @template T
     * @param  Closure(): T  $callback
     * @return T
     */
    public function rememberDashboard(int|string $userId, Closure $callback, ?int $ttlSeconds = null): mixed
    {
        return $this->remember(CacheKey::userDashboard($userId), $callback, $ttlSeconds);
    }

    /**
     * @template T
     * @param  Closure(): T  $callback
     * @return T
     */
    public function rememberFavorites(int|string $userId, Closure $callback, ?int $ttlSeconds = null): mixed
    {
        return $this->remember(CacheKey::userFavorites($userId), $callback, $ttlSeconds);
    }

    public function forgetProfile(int|string $userId): void
    {
        $this->forget(CacheKey::userProfile($userId));
    }

    public function forgetProgress(int|string $userId): void
    {
        $this->forget(CacheKey::userProgress($userId));
    }

    public function forgetDashboard(int|string $userId): void
    {
        $this->forget(CacheKey::userDashboard($userId));
    }

    public function forgetFavorites(int|string $userId): void
    {
        $this->forget(CacheKey::userFavorites($userId));
    }

    /**
     * Invalidate common user-facing caches after progress / approval changes.
     */
    public function forgetProgressRelated(int|string $userId): void
    {
        $this->forgetProgress($userId);
        $this->forgetDashboard($userId);
    }

    protected function defaultTtl(): int
    {
        return 600;
    }
}
