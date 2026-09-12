<?php

namespace App\Services\Cache;

/**
 * Centralized, versioned cache key builder.
 *
 * Curriculum keys are global (safe for public routes).
 * User keys MUST always include the user id — never cache personalized
 * data under a global key.
 */
final class CacheKey
{
    public const VERSION = 'v1';

    public static function curriculum(string ...$parts): string
    {
        return self::join('curriculum', self::VERSION, ...$parts);
    }

    public static function curriculumLanguage(string $language): string
    {
        return self::curriculum('language', $language);
    }

    public static function curriculumLevel(int|string $levelId): string
    {
        return self::curriculum('level', (string) $levelId);
    }

    public static function curriculumLesson(int|string $lessonId, ?string $language = null): string
    {
        if ($language === null) {
            return self::curriculum('lesson', (string) $lessonId);
        }

        return self::curriculum('lesson', (string) $lessonId, 'lang', $language);
    }

    public static function curriculumLessonTranslations(int|string $lessonId): string
    {
        return self::curriculum('lesson', (string) $lessonId, 'translations');
    }

    public static function user(int|string $userId, string ...$parts): string
    {
        return self::join('user', self::VERSION, (string) $userId, ...$parts);
    }

    public static function userProfile(int|string $userId): string
    {
        return self::user($userId, 'profile');
    }

    public static function userProgress(int|string $userId): string
    {
        return self::user($userId, 'progress');
    }

    public static function userDashboard(int|string $userId): string
    {
        return self::user($userId, 'dashboard');
    }

    public static function userFavorites(int|string $userId): string
    {
        return self::user($userId, 'favorites');
    }

    public static function userGoals(int|string $userId): string
    {
        return self::user($userId, 'goals');
    }

    public static function userSessions(int|string $userId): string
    {
        return self::user($userId, 'sessions');
    }

    public static function quiz(string ...$parts): string
    {
        return self::join('quiz', self::VERSION, ...$parts);
    }

    public static function quizLevel(int|string $levelId): string
    {
        return self::quiz('level', (string) $levelId);
    }

    /**
     * Prefix used for pattern-style invalidation of lesson caches.
     * Example: curriculum:v1:lesson:12
     */
    public static function curriculumLessonPrefix(int|string $lessonId): string
    {
        return self::curriculum('lesson', (string) $lessonId);
    }

    private static function join(string ...$parts): string
    {
        return implode(':', array_map(static fn (string $part): string => trim($part, ':'), $parts));
    }
}
