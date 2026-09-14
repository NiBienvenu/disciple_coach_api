<?php

namespace App\Services\Progress;

use App\Enums\ContentStatus;
use App\Enums\LessonProgressStatus;
use App\Enums\PreferredLanguage;
use App\Http\Resources\Progress\DashboardResource;
use App\Http\Resources\Progress\LessonProgressResource;
use App\Http\Resources\Progress\LevelProgressResource;
use App\Http\Resources\Progress\QuizAttemptResource;
use App\Models\LessonProgress;
use App\Models\Level;
use App\Models\LevelProgress;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Services\Cache\UserCacheService;

class DashboardService
{
    public function __construct(
        private readonly UserCacheService $userCache,
        private readonly LevelProgressionService $progression,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getDashboard(User $user, ?string $language = null): array
    {
        $lang = PreferredLanguage::resolve(
            $language ?? ($user->preferred_language instanceof \BackedEnum
                ? $user->preferred_language->value
                : $user->preferred_language)
        )->value;

        return $this->userCache->rememberDashboard($user->id, function () use ($user, $lang) {
            $this->progression->ensureBootstrap($user);

            $levels = Level::query()
                ->where('status', ContentStatus::Published)
                ->orderBy('order')
                ->with([
                    'translations' => fn ($q) => $q->where('language', $lang),
                    'lessons' => fn ($q) => $q
                        ->select(['id', 'level_id', 'code', 'slug', 'order', 'status'])
                        ->where('status', ContentStatus::Published)
                        ->orderBy('order'),
                ])
                ->get(['id', 'slug', 'order', 'previous_level_id', 'status']);

            $levelProgress = LevelProgress::query()
                ->where('user_id', $user->id)
                ->get()
                ->keyBy('level_id');

            $lessonIds = $levels->flatMap(fn (Level $l) => $l->lessons->pluck('id'))->all();

            $lessonProgress = LessonProgress::query()
                ->where('user_id', $user->id)
                ->whereIn('lesson_id', $lessonIds !== [] ? $lessonIds : [0])
                ->get()
                ->keyBy('lesson_id');

            $attempts = QuizAttempt::query()
                ->where('user_id', $user->id)
                ->get()
                ->keyBy('level_id');

            $completedLessons = $lessonProgress
                ->filter(fn (LessonProgress $p) => $p->status === LessonProgressStatus::Completed)
                ->count();

            $levelsPayload = $levels->map(function (Level $level) use ($user, $levelProgress, $lessonProgress, $attempts, $lang) {
                $lp = $levelProgress->get($level->id);
                $attempt = $attempts->get($level->id);
                $translation = $level->translationFor($lang);
                $levelLessonIds = $level->lessons->pluck('id');
                $completed = $levelLessonIds->filter(
                    fn ($id) => ($lessonProgress->get($id)?->status === LessonProgressStatus::Completed)
                )->count();

                return [
                    'level_id' => $level->id,
                    'slug' => $level->slug,
                    'order' => $level->order,
                    'title' => $translation?->title,
                    'status' => $lp?->status instanceof \BackedEnum
                        ? $lp->status->value
                        : ($lp?->status ?? 'locked'),
                    'lessons_completed' => $completed,
                    'lessons_total' => $levelLessonIds->count(),
                    'can_take_quiz' => $this->progression->canTakeQuiz($user, $level),
                    'quiz_attempt' => $attempt !== null
                        ? [
                            'id' => $attempt->id,
                            'score' => $attempt->score !== null ? (float) $attempt->score : null,
                            'mentor_status' => $attempt->mentor_status instanceof \BackedEnum
                                ? $attempt->mentor_status->value
                                : $attempt->mentor_status,
                            'advancement_approved' => (bool) $attempt->advancement_approved,
                            'submitted_at' => $attempt->submitted_at?->toIso8601String(),
                        ]
                        : null,
                    'approved_at' => $lp?->approved_at?->toIso8601String(),
                ];
            })->values()->all();

            return (new DashboardResource([
                'completed_lessons_count' => $completedLessons,
                'total_lessons_count' => count($lessonIds),
                'levels' => $levelsPayload,
            ]))->resolve();
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getLessonProgress(User $user): array
    {
        $this->progression->ensureBootstrap($user);

        return $this->userCache->rememberProgress($user->id, function () use ($user) {
            $rows = LessonProgress::query()
                ->where('user_id', $user->id)
                ->with(['lesson:id,level_id,code,slug,order'])
                ->orderBy('id')
                ->get();

            return $rows->map(
                fn (LessonProgress $row) => (new LessonProgressResource($row))->resolve()
            )->values()->all();
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getLevelProgress(User $user): array
    {
        $this->progression->ensureBootstrap($user);

        $rows = LevelProgress::query()
            ->where('user_id', $user->id)
            ->with(['level:id,slug,order,previous_level_id'])
            ->get()
            ->sortBy(fn (LevelProgress $p) => $p->level?->order ?? 0)
            ->values();

        return $rows->map(
            fn (LevelProgress $row) => (new LevelProgressResource($row))->resolve()
        )->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getQuizAttemptsForUser(User $user, bool $forCoachReview = false, ?string $language = null): array
    {
        $lang = PreferredLanguage::resolve($language)->value;

        $attempts = QuizAttempt::query()
            ->where('user_id', $user->id)
            ->with([
                'answers.question.translations' => fn ($q) => $q->where('language', $lang),
                'level:id,slug,order',
            ])
            ->orderByDesc('submitted_at')
            ->get();

        return $attempts->map(
            fn (QuizAttempt $attempt) => (new QuizAttemptResource($attempt, $forCoachReview, $lang))->resolve()
        )->values()->all();
    }
}
