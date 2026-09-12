<?php

namespace Tests\Unit\Services\Cache;

use App\Services\Cache\CacheKey;
use PHPUnit\Framework\TestCase;

class CacheKeyTest extends TestCase
{
    public function test_curriculum_keys_are_versioned_and_stable(): void
    {
        $this->assertSame('curriculum:v1:language:fr', CacheKey::curriculumLanguage('fr'));
        $this->assertSame('curriculum:v1:language:rn', CacheKey::curriculumLanguage('rn'));
        $this->assertSame('curriculum:v1:language:en', CacheKey::curriculumLanguage('en'));
        $this->assertSame('curriculum:v1:level:2', CacheKey::curriculumLevel(2));
        $this->assertSame('curriculum:v1:lesson:12', CacheKey::curriculumLesson(12));
        $this->assertSame('curriculum:v1:lesson:12:lang:fr', CacheKey::curriculumLesson(12, 'fr'));
        $this->assertSame('curriculum:v1:lesson:12:translations', CacheKey::curriculumLessonTranslations(12));
        $this->assertSame('curriculum:v1:lesson:12', CacheKey::curriculumLessonPrefix(12));
    }

    public function test_user_keys_always_include_user_id(): void
    {
        $this->assertSame('user:v1:42:profile', CacheKey::userProfile(42));
        $this->assertSame('user:v1:42:progress', CacheKey::userProgress(42));
        $this->assertSame('user:v1:42:dashboard', CacheKey::userDashboard(42));
        $this->assertSame('user:v1:42:favorites', CacheKey::userFavorites(42));
        $this->assertSame('user:v1:42:goals', CacheKey::userGoals(42));
        $this->assertSame('user:v1:42:sessions', CacheKey::userSessions(42));
    }

    public function test_quiz_keys_are_versioned(): void
    {
        $this->assertSame('quiz:v1:level:3', CacheKey::quizLevel(3));
    }

    public function test_version_constant_is_v1(): void
    {
        $this->assertSame('v1', CacheKey::VERSION);
    }
}
