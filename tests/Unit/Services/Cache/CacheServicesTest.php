<?php

namespace Tests\Unit\Services\Cache;

use App\Services\Cache\CurriculumCacheService;
use App\Services\Cache\UserCacheService;
use Tests\TestCase;

class CacheServicesTest extends TestCase
{
    public function test_curriculum_cache_remembers_and_forgets_language_payload(): void
    {
        $cache = new CurriculumCacheService;

        $value = $cache->rememberLanguage('fr', fn () => ['levels' => 5]);

        $this->assertSame(['levels' => 5], $value);
        $this->assertSame(['levels' => 5], $cache->rememberLanguage('fr', fn () => ['levels' => 999]));

        $cache->forgetLanguage('fr');

        $this->assertSame(['levels' => 1], $cache->rememberLanguage('fr', fn () => ['levels' => 1]));
    }

    public function test_user_cache_isolates_favorites_per_user(): void
    {
        $cache = new UserCacheService;

        $cache->rememberFavorites(1, fn () => ['lesson_ids' => [10]]);
        $cache->rememberFavorites(2, fn () => ['lesson_ids' => [20]]);

        $this->assertSame(['lesson_ids' => [10]], $cache->rememberFavorites(1, fn () => ['lesson_ids' => []]));
        $this->assertSame(['lesson_ids' => [20]], $cache->rememberFavorites(2, fn () => ['lesson_ids' => []]));

        $cache->forgetFavorites(1);

        $this->assertSame(['lesson_ids' => [99]], $cache->rememberFavorites(1, fn () => ['lesson_ids' => [99]]));
        $this->assertSame(['lesson_ids' => [20]], $cache->rememberFavorites(2, fn () => ['lesson_ids' => []]));
    }

    public function test_forget_progress_related_clears_progress_and_dashboard(): void
    {
        $cache = new UserCacheService;

        $cache->rememberProgress(7, fn () => ['completed' => 3]);
        $cache->rememberDashboard(7, fn () => ['next_lesson' => 4]);

        $cache->forgetProgressRelated(7);

        $this->assertSame(['completed' => 0], $cache->rememberProgress(7, fn () => ['completed' => 0]));
        $this->assertSame(['next_lesson' => null], $cache->rememberDashboard(7, fn () => ['next_lesson' => null]));
    }
}
