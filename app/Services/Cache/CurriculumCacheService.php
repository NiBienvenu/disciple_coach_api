<?php

namespace App\Services\Cache;

use Closure;

/**
 * Global curriculum cache — safe for public (unauthenticated) read routes.
 *
 * MUST NOT store user-specific fields (is_favorite, progress, completed_at, etc.).
 * Favorites and progress belong in UserCacheService under user:{id}:... keys.
 */
class CurriculumCacheService extends AbstractCacheService
{
    /**
     * @template T
     * @param  Closure(): T  $callback
     * @return T
     */
    public function rememberLanguage(string $language, Closure $callback, ?int $ttlSeconds = null): mixed
    {
        return $this->rememberStaleWhileRevalidate(
            CacheKey::curriculumLanguage($language),
            $callback,
            $ttlSeconds ?? $this->defaultTtl(),
        );
    }

    /**
     * @template T
     * @param  Closure(): T  $callback
     * @return T
     */
    public function rememberLevel(int|string $levelId, Closure $callback, ?int $ttlSeconds = null): mixed
    {
        return $this->rememberStaleWhileRevalidate(
            CacheKey::curriculumLevel($levelId),
            $callback,
            $ttlSeconds ?? $this->defaultTtl(),
        );
    }

    /**
     * @template T
     * @param  Closure(): T  $callback
     * @return T
     */
    public function rememberLesson(
        int|string $lessonId,
        ?string $language,
        Closure $callback,
        ?int $ttlSeconds = null,
    ): mixed {
        return $this->rememberStaleWhileRevalidate(
            CacheKey::curriculumLesson($lessonId, $language),
            $callback,
            $ttlSeconds ?? $this->defaultTtl(),
        );
    }

    public function forgetLanguage(string $language): void
    {
        $this->forget(CacheKey::curriculumLanguage($language));
    }

    public function forgetLevel(int|string $levelId): void
    {
        $this->forget(CacheKey::curriculumLevel($levelId));
    }

    /**
     * Invalidate a single lesson and its language variants without flushing curriculum.
     */
    public function forgetLesson(int|string $lessonId): void
    {
        $this->forget(CacheKey::curriculumLesson($lessonId));
        $this->forget(CacheKey::curriculumLessonTranslations($lessonId));
        $this->forgetByPrefix(CacheKey::curriculumLessonPrefix($lessonId));
    }

    protected function defaultTtl(): int
    {
        // Curriculum changes infrequently — longer TTL; SWR refreshes in background later.
        return 86400;
    }
}
