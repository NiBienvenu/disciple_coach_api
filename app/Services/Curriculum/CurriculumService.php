<?php

namespace App\Services\Curriculum;

use App\Enums\ContentStatus;
use App\Http\Resources\Curriculum\LessonResource;
use App\Http\Resources\Curriculum\LessonSummaryResource;
use App\Http\Resources\Curriculum\LevelResource;
use App\Http\Resources\Curriculum\QuizResource;
use App\Models\Lesson;
use App\Models\Level;
use App\Models\Quiz;
use App\Services\Cache\CacheKey;
use App\Services\Cache\CurriculumCacheService;
use Illuminate\Database\Eloquent\Collection;

class CurriculumService
{
    public function __construct(private readonly CurriculumCacheService $cache) {}

    /**
     * Full curriculum tree for a language (levels + lesson summaries).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getCurriculum(string $language): array
    {
        return $this->cache->rememberLanguage($language, function () use ($language) {
            $levels = $this->publishedLevelsQuery()
                ->with([
                    'translations' => fn ($q) => $q->select([
                        'id', 'level_id', 'language', 'title', 'description',
                    ])->where('language', $language),
                    'lessons' => fn ($q) => $q->select([
                        'id', 'level_id', 'code', 'slug', 'type', 'order', 'status', 'resource_url',
                    ])
                        ->where('status', ContentStatus::Published)
                        ->orderBy('order'),
                    'lessons.translations' => fn ($q) => $q->select([
                        'id', 'lesson_id', 'language', 'title',
                    ])->where('language', $language),
                ])
                ->get();

            return $levels->map(
                fn (Level $level) => (new LevelResource($level, $language, withLessons: true))->resolve()
            )->values()->all();
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getLevels(string $language): array
    {
        return $this->cache->remember(
            CacheKey::curriculum('levels', $language),
            function () use ($language) {
                $levels = $this->publishedLevelsQuery()
                    ->with([
                        'translations' => fn ($q) => $q->select([
                            'id', 'level_id', 'language', 'title', 'description',
                        ])->where('language', $language),
                    ])
                    ->get();

                return $levels->map(
                    fn (Level $level) => (new LevelResource($level, $language))->resolve()
                )->values()->all();
            }
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getLevel(int|string $levelId, string $language, bool $withLessons = true): ?array
    {
        return $this->cache->rememberLevel(
            "{$levelId}:{$language}:".($withLessons ? 'full' : 'summary'),
            function () use ($levelId, $language, $withLessons) {
                $query = $this->publishedLevelsQuery()
                    ->where(function ($q) use ($levelId) {
                        $q->where('id', $levelId)->orWhere('slug', $levelId);
                    })
                    ->with([
                        'translations' => fn ($q) => $q->select([
                            'id', 'level_id', 'language', 'title', 'description',
                        ])->where('language', $language),
                    ]);

                if ($withLessons) {
                    $query->with([
                        'lessons' => fn ($q) => $q->select([
                            'id', 'level_id', 'code', 'slug', 'type', 'order', 'status', 'resource_url',
                        ])
                            ->where('status', ContentStatus::Published)
                            ->orderBy('order'),
                        'lessons.translations' => fn ($q) => $q->select([
                            'id', 'lesson_id', 'language', 'title',
                        ])->where('language', $language),
                    ]);
                }

                $level = $query->first();

                if ($level === null) {
                    return null;
                }

                return (new LevelResource($level, $language, withLessons: $withLessons))->resolve();
            }
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getLessonsForLevel(int|string $levelId, string $language): array
    {
        $level = $this->findPublishedLevel($levelId);

        if ($level === null) {
            return [];
        }

        return $this->cache->remember(
            CacheKey::curriculum('level', (string) $level->id, 'lessons', $language),
            function () use ($level, $language) {
                /** @var Collection<int, Lesson> $lessons */
                $lessons = Lesson::query()
                    ->select(['id', 'level_id', 'code', 'slug', 'type', 'order', 'status', 'resource_url'])
                    ->where('level_id', $level->id)
                    ->where('status', ContentStatus::Published)
                    ->orderBy('order')
                    ->with([
                        'translations' => fn ($q) => $q->select([
                            'id', 'lesson_id', 'language', 'title',
                        ])->where('language', $language),
                    ])
                    ->get();

                return $lessons->map(
                    fn (Lesson $lesson) => (new LessonSummaryResource($lesson, $language))->resolve()
                )->values()->all();
            }
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getLesson(int|string $lessonId, string $language): ?array
    {
        return $this->cache->rememberLesson($lessonId, $language, function () use ($lessonId, $language) {
            $lesson = Lesson::query()
                ->select([
                    'id', 'level_id', 'code', 'slug', 'type', 'order', 'status', 'resource_url',
                ])
                ->where('status', ContentStatus::Published)
                ->where(function ($q) use ($lessonId) {
                    $q->where('id', $lessonId)->orWhere('slug', $lessonId)->orWhere('code', $lessonId);
                })
                ->with([
                    'translations' => fn ($q) => $q->select([
                        'id',
                        'lesson_id',
                        'language',
                        'title',
                        'central_idea',
                        'objectives',
                        'key_scriptures',
                        'summary_points',
                        'reflection_questions',
                    ])->where('language', $language),
                ])
                ->first();

            if ($lesson === null) {
                return null;
            }

            return (new LessonResource($lesson, $language))->resolve();
        });
    }

    /**
     * Public quiz payload — never includes correct_index.
     *
     * @return array<string, mixed>|null
     */
    public function getQuizForLevel(int|string $levelId, string $language): ?array
    {
        $level = $this->findPublishedLevel($levelId);

        if ($level === null) {
            return null;
        }

        return $this->cache->remember(
            CacheKey::quiz('level', (string) $level->id, 'lang', $language),
            function () use ($level, $language) {
                $quiz = Quiz::query()
                    ->select(['id', 'level_id', 'status'])
                    ->where('level_id', $level->id)
                    ->where('status', ContentStatus::Published)
                    ->with([
                        'questions' => fn ($q) => $q->select([
                            'id', 'quiz_id', 'order',
                        ])->orderBy('order'),
                        'questions.translations' => fn ($q) => $q->select([
                            'id', 'quiz_question_id', 'language', 'prompt', 'choices',
                        ])->where('language', $language),
                    ])
                    ->first();

                if ($quiz === null) {
                    return null;
                }

                return (new QuizResource($quiz, $language))->resolve();
            }
        );
    }

    public function findPublishedLevel(int|string $levelId): ?Level
    {
        return Level::query()
            ->select(['id', 'slug', 'order', 'previous_level_id', 'status'])
            ->where('status', ContentStatus::Published)
            ->where(function ($q) use ($levelId) {
                $q->where('id', $levelId)->orWhere('slug', $levelId);
            })
            ->first();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Level>
     */
    private function publishedLevelsQuery()
    {
        return Level::query()
            ->select(['id', 'slug', 'order', 'previous_level_id', 'status'])
            ->where('status', ContentStatus::Published)
            ->orderBy('order');
    }
}
