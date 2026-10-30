<?php

namespace App\Services\Progress;

use App\Enums\LessonProgressStatus;
use App\Models\CoachDiscipleRelationship;
use App\Models\Lesson;
use App\Models\LessonPage;
use App\Models\LessonQuizAttempt;
use App\Models\User;
use App\Services\Cache\UserCacheService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Answers to the questions at the end of a lesson. Sending them completes
 * the lesson; the next lesson opens once the coach approves them. The coach
 * can also reset them so the disciple answers again.
 */
class LessonQuizService
{
    public function __construct(
        private readonly LevelProgressionService $progression,
        private readonly UserCacheService $userCache,
    ) {}

    /**
     * @param  array<int, array{page_id: int, selected_index: int}>  $answers
     */
    public function submit(User $user, Lesson $lesson, array $answers): LessonQuizAttempt
    {
        $lesson->loadMissing('level');
        if (! $this->progression->isLevelAccessible($user, $lesson->level)) {
            throw new AuthorizationException('This level is locked.');
        }
        $this->progression->assertLessonOpen($user, $lesson);

        $questions = $this->questions($lesson);
        if ($questions->isEmpty()) {
            throw ValidationException::withMessages(['answers' => ['This lesson has no questions.']]);
        }

        $chosen = [];
        foreach ($answers as $row) {
            $chosen[(int) $row['page_id']] = (int) $row['selected_index'];
        }
        if (array_diff($questions->keys()->all(), array_keys($chosen)) !== []) {
            throw ValidationException::withMessages(['answers' => ['Answer every question of the lesson.']]);
        }

        $existing = LessonQuizAttempt::query()
            ->where('user_id', $user->id)
            ->where('lesson_id', $lesson->id)
            ->first();
        if ($existing?->isApproved()) {
            // Already approved: nothing to send again.
            return $existing;
        }

        $graded = [];
        foreach ($questions as $page) {
            $selected = $chosen[$page->id];
            $graded[] = [
                'page_id' => $page->id,
                'selected_index' => $selected,
                'correct_index' => (int) $page->correct_index,
                'is_correct' => $selected === (int) $page->correct_index,
            ];
        }
        $correct = count(array_filter($graded, fn ($g) => $g['is_correct']));
        $relationship = $this->progression->activeRelationshipForDisciple($user);

        $attempt = DB::transaction(function () use ($user, $lesson, $graded, $correct, $relationship) {
            $attempt = LessonQuizAttempt::query()->updateOrCreate(
                ['user_id' => $user->id, 'lesson_id' => $lesson->id],
                [
                    'coach_id' => $relationship?->coach_id,
                    'relationship_id' => $relationship?->id,
                    'status' => LessonQuizAttempt::PENDING,
                    'score' => round($correct / count($graded), 4),
                    'answers' => $graded,
                    'feedback' => null,
                    'submitted_at' => now(),
                    'decided_at' => null,
                ],
            );
            $this->progression->completeLesson($user, $lesson);

            return $attempt;
        });

        return $attempt->fresh();
    }

    /** Coach decision: `approve` opens the next lesson, `reset` asks for new answers. */
    public function decide(User $coach, LessonQuizAttempt $attempt, string $decision, ?string $feedback): LessonQuizAttempt
    {
        $this->assertCoachCanReview($coach, $attempt);

        return DB::transaction(function () use ($coach, $attempt, $decision, $feedback) {
            $attempt->fill([
                'status' => $decision === 'approve' ? LessonQuizAttempt::APPROVED : LessonQuizAttempt::RESET,
                'feedback' => $feedback,
                'coach_id' => $coach->id,
                'decided_at' => now(),
            ])->save();

            if ($decision === 'reset') {
                // The lesson is open again until new answers are sent.
                $this->progression->completeLesson(
                    $attempt->user,
                    $attempt->lesson,
                    LessonProgressStatus::InProgress,
                );
            }
            $this->userCache->forgetProgressRelated($attempt->user_id);

            return $attempt->fresh();
        });
    }

    public function canReview(User $coach, LessonQuizAttempt $attempt): bool
    {
        return $coach->id !== $attempt->user_id
            && $coach->isApprovedCoach()
            && CoachDiscipleRelationship::query()
                ->active()
                ->where('coach_id', $coach->id)
                ->where('disciple_id', $attempt->user_id)
                ->exists();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function attemptsFor(User $disciple, string $language): array
    {
        $attempts = LessonQuizAttempt::query()
            ->where('user_id', $disciple->id)
            ->with('lesson:id,level_id,code,order')
            ->orderByDesc('submitted_at')
            ->get();

        $pages = LessonPage::query()
            ->whereIn('lesson_id', $attempts->pluck('lesson_id'))
            ->where('kind', LessonPage::KIND_QUESTION)
            ->with('translations')
            ->get()
            ->keyBy('id');

        return $attempts->map(fn (LessonQuizAttempt $a) => $this->payload($a, $pages, $language))->all();
    }

    /**
     * @param  Collection<int, LessonPage>  $pages
     * @return array<string, mixed>
     */
    public function payload(LessonQuizAttempt $attempt, Collection $pages, string $language): array
    {
        return [
            'id' => $attempt->id,
            'lesson_id' => $attempt->lesson_id,
            'level_id' => $attempt->lesson?->level_id,
            'status' => $attempt->status,
            'score' => $attempt->score,
            'feedback' => $attempt->feedback,
            'submitted_at' => $attempt->submitted_at?->toIso8601String(),
            'decided_at' => $attempt->decided_at?->toIso8601String(),
            'answers' => array_map(function (array $answer) use ($pages, $language) {
                $t = $pages->get($answer['page_id'])?->translationFor($language);

                return [
                    ...$answer,
                    'prompt' => $t?->title,
                    'choices' => $t?->choices ?? [],
                ];
            }, $attempt->answers ?? []),
        ];
    }

    /** @return Collection<int, LessonPage> question pages keyed by id */
    private function questions(Lesson $lesson): Collection
    {
        return LessonPage::query()
            ->where('lesson_id', $lesson->id)
            ->where('kind', LessonPage::KIND_QUESTION)
            ->orderBy('order')
            ->get()
            ->keyBy('id');
    }

    private function assertCoachCanReview(User $coach, LessonQuizAttempt $attempt): void
    {
        if (! $this->canReview($coach, $attempt)) {
            throw new AuthorizationException('You can only review answers of your assigned disciples.');
        }
    }
}
