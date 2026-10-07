<?php

namespace App\Services\Progress;

use App\Enums\ContentStatus;
use App\Enums\LessonProgressStatus;
use App\Enums\LevelProgressStatus;
use App\Enums\MentorReviewStatus;
use App\Enums\QuizAttemptStatus;
use App\Models\CoachDiscipleRelationship;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Level;
use App\Models\LevelProgress;
use App\Models\Quiz;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\User;
use App\Services\Cache\UserCacheService;
use App\Services\Certificates\CertificateService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LevelProgressionService
{
    public function __construct(
        private readonly UserCacheService $userCache,
        private readonly CertificateService $certificates,
    ) {}

    /**
     * Level 1 available by default; other levels locked until previous advancement_approved.
     */
    public function ensureBootstrap(User $user): void
    {
        $this->ensureLevelProgressBootstrap($user);
    }

    public function ensureLevelProgressBootstrap(User $user): void
    {
        $levels = Level::query()
            ->where('status', ContentStatus::Published)
            ->orderBy('order')
            ->get(['id', 'order', 'previous_level_id']);

        if ($levels->isEmpty()) {
            return;
        }

        $approvedLevelIds = QuizAttempt::query()
            ->where('user_id', $user->id)
            ->where('advancement_approved', true)
            ->pluck('level_id')
            ->all();

        $firstLevelId = $levels->first()->id;

        foreach ($levels as $level) {
            $unlocked = $level->id === $firstLevelId
                || ($level->previous_level_id !== null && in_array($level->previous_level_id, $approvedLevelIds, true));

            $progress = LevelProgress::query()->firstOrCreate(
                [
                    'user_id' => $user->id,
                    'level_id' => $level->id,
                ],
                [
                    'status' => $unlocked
                        ? LevelProgressStatus::Available
                        : LevelProgressStatus::Locked,
                ],
            );

            if (
                $unlocked
                && $progress->status === LevelProgressStatus::Locked
            ) {
                $progress->update(['status' => LevelProgressStatus::Available]);
            }
        }
    }

    public function isLevelAccessible(User $user, Level $level): bool
    {
        $this->ensureBootstrap($user);

        if ($level->previous_level_id === null) {
            $first = Level::query()
                ->where('status', ContentStatus::Published)
                ->orderBy('order')
                ->value('id');

            if ($first === $level->id) {
                return true;
            }
        }

        if ($level->previous_level_id !== null) {
            $previousApproved = QuizAttempt::query()
                ->where('user_id', $user->id)
                ->where('level_id', $level->previous_level_id)
                ->where('advancement_approved', true)
                ->exists();

            if ($previousApproved) {
                return true;
            }
        }

        $progress = LevelProgress::query()
            ->where('user_id', $user->id)
            ->where('level_id', $level->id)
            ->first();

        return $progress !== null
            && $progress->status !== LevelProgressStatus::Locked;
    }

    /**
     * Explicit complete action. Idempotent for unique user+lesson.
     */
    public function completeLesson(
        User $user,
        Lesson $lesson,
        LessonProgressStatus $status = LessonProgressStatus::Completed,
    ): LessonProgress {
        $this->ensureBootstrap($user);

        $lesson->loadMissing('level');

        if (! $this->isLevelAccessible($user, $lesson->level)) {
            throw new AuthorizationException('This level is locked. Complete and get approval on the previous level first.');
        }

        $relationship = $this->activeRelationshipForDisciple($user);

        return DB::transaction(function () use ($user, $lesson, $status, $relationship) {
            /** @var LessonProgress $progress */
            $progress = LessonProgress::query()->firstOrNew([
                'user_id' => $user->id,
                'lesson_id' => $lesson->id,
            ]);

            if (
                $progress->exists
                && $progress->status === LessonProgressStatus::Completed
                && $status === LessonProgressStatus::Completed
            ) {
                return $progress;
            }

            $now = now();

            if ($status === LessonProgressStatus::Completed) {
                $progress->status = LessonProgressStatus::Completed;
                $progress->completed_at = $progress->completed_at ?? $now;
                $progress->started_at = $progress->started_at ?? $now;
            } else {
                $progress->status = $status;
                $progress->started_at = $progress->started_at ?? $now;
                if ($status !== LessonProgressStatus::Completed) {
                    $progress->completed_at = null;
                }
            }

            $progress->last_viewed_at = $now;
            $progress->coach_id = $relationship?->coach_id;
            $progress->save();

            $this->syncLevelProgressAfterLesson($user, $lesson->level);
            $this->userCache->forgetProgressRelated($user->id);

            return $progress->fresh(['lesson']);
        });
    }

    public function canTakeQuiz(User $user, Level $level): bool
    {
        if (! $this->isLevelAccessible($user, $level)) {
            return false;
        }

        $lessonIds = Lesson::query()
            ->where('level_id', $level->id)
            ->where('status', ContentStatus::Published)
            ->pluck('id');

        if ($lessonIds->isEmpty()) {
            return true;
        }

        $completed = LessonProgress::query()
            ->where('user_id', $user->id)
            ->whereIn('lesson_id', $lessonIds)
            ->where('status', LessonProgressStatus::Completed)
            ->count();

        return $completed === $lessonIds->count();
    }

    /**
     * @param  array<int, array{question_id: int, selected_index: int}>  $answers
     */
    public function submitQuiz(User $user, Level $level, array $answers): QuizAttempt
    {
        $this->ensureBootstrap($user);

        if (! $this->isLevelAccessible($user, $level)) {
            throw new AuthorizationException('This level is locked. You cannot submit this quiz yet.');
        }

        if (! $this->canTakeQuiz($user, $level)) {
            throw new AuthorizationException('Complete all lessons of this level before taking the quiz.');
        }

        $quiz = Quiz::query()
            ->where('level_id', $level->id)
            ->where('status', ContentStatus::Published)
            ->with('questions')
            ->first();

        if ($quiz === null || $quiz->questions->isEmpty()) {
            throw ValidationException::withMessages([
                'quiz' => ['No published quiz exists for this level.'],
            ]);
        }

        $questionsById = $quiz->questions->keyBy('id');
        $answerMap = [];

        foreach ($answers as $row) {
            $questionId = (int) $row['question_id'];
            if (! $questionsById->has($questionId)) {
                throw ValidationException::withMessages([
                    'answers' => ["Question {$questionId} does not belong to this quiz."],
                ]);
            }
            $answerMap[$questionId] = (int) $row['selected_index'];
        }

        foreach ($questionsById as $question) {
            if (! array_key_exists($question->id, $answerMap)) {
                throw ValidationException::withMessages([
                    'answers' => ['All quiz questions must be answered.'],
                ]);
            }
        }

        $relationship = $this->activeRelationshipForDisciple($user);

        return DB::transaction(function () use ($user, $level, $quiz, $questionsById, $answerMap, $relationship) {
            $correctCount = 0;
            $graded = [];

            /** @var QuizQuestion $question */
            foreach ($questionsById as $question) {
                $selected = $answerMap[$question->id];
                $isCorrect = $selected === (int) $question->correct_index;
                if ($isCorrect) {
                    $correctCount++;
                }
                $graded[] = [
                    'quiz_question_id' => $question->id,
                    'selected_index' => $selected,
                    'correct_index' => (int) $question->correct_index,
                    'is_correct' => $isCorrect,
                ];
            }

            $score = round($correctCount / $questionsById->count(), 4);

            /** @var QuizAttempt $attempt */
            $attempt = QuizAttempt::query()->firstOrNew([
                'user_id' => $user->id,
                'level_id' => $level->id,
            ]);

            $wasAdvancementApproved = (bool) $attempt->advancement_approved;
            $advancementApprovedAt = $attempt->advancement_approved_at;

            $attempt->fill([
                'quiz_id' => $quiz->id,
                'coach_id' => $relationship?->coach_id ?? $attempt->coach_id,
                'relationship_id' => $relationship?->id ?? $attempt->relationship_id,
                'status' => QuizAttemptStatus::Submitted,
                'score' => $score,
                'mentor_status' => MentorReviewStatus::Pending,
                'submitted_at' => now(),
                // Retake never clears the one-way advancement ratchet.
                'advancement_approved' => $wasAdvancementApproved,
                'advancement_approved_at' => $wasAdvancementApproved ? $advancementApprovedAt : null,
            ]);

            if (! $wasAdvancementApproved) {
                $attempt->mentor_feedback = null;
                $attempt->decided_at = null;
            }

            $attempt->save();

            QuizAnswer::query()->where('quiz_attempt_id', $attempt->id)->delete();

            foreach ($graded as $row) {
                QuizAnswer::query()->create([
                    'quiz_attempt_id' => $attempt->id,
                    ...$row,
                ]);
            }

            // Retake after advancement approval must not regress level status.
            if (! $wasAdvancementApproved) {
                LevelProgress::query()->updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'level_id' => $level->id,
                    ],
                    [
                        'status' => LevelProgressStatus::CoachReview,
                        'completed_at' => now(),
                    ],
                );
            }

            $this->userCache->forgetProgressRelated($user->id);

            return $attempt->fresh(['answers', 'quiz', 'level']);
        });
    }

    public function approveLevel(User $coach, QuizAttempt $attempt, ?string $feedback = null): QuizAttempt
    {
        $this->assertCoachCanReview($coach, $attempt);

        return DB::transaction(function () use ($coach, $attempt, $feedback) {
            $attempt->refresh();

            $attempt->status = QuizAttemptStatus::Approved;
            $attempt->mentor_status = MentorReviewStatus::Approved;
            $attempt->mentor_feedback = $feedback;
            $attempt->decided_at = now();
            $attempt->coach_id = $coach->id;
            $attempt->advancement_approved = true;
            $attempt->advancement_approved_at = $attempt->advancement_approved_at ?? now();
            $attempt->save();

            LevelProgress::query()->updateOrCreate(
                [
                    'user_id' => $attempt->user_id,
                    'level_id' => $attempt->level_id,
                ],
                [
                    'status' => LevelProgressStatus::Approved,
                    'approved_at' => now(),
                    'completed_at' => now(),
                ],
            );

            $level = Level::query()->findOrFail($attempt->level_id);
            $disciple = User::query()->findOrFail($attempt->user_id);
            $this->unlockNextLevel($disciple, $level);
            $this->certificates->issueIfEligible($disciple, $attempt, $level);

            $this->userCache->forgetProgressRelated($attempt->user_id);

            return $attempt->fresh(['answers', 'quiz', 'level']);
        });
    }

    public function rejectLevel(User $coach, QuizAttempt $attempt, ?string $feedback = null): QuizAttempt
    {
        $this->assertCoachCanReview($coach, $attempt);

        return DB::transaction(function () use ($coach, $attempt, $feedback) {
            $attempt->refresh();

            $attempt->status = QuizAttemptStatus::Rejected;
            $attempt->mentor_status = MentorReviewStatus::ChangesRequested;
            $attempt->mentor_feedback = $feedback;
            $attempt->decided_at = now();
            $attempt->coach_id = $coach->id;
            // Never clear advancement_approved on reject either.
            $attempt->save();

            LevelProgress::query()->updateOrCreate(
                [
                    'user_id' => $attempt->user_id,
                    'level_id' => $attempt->level_id,
                ],
                [
                    'status' => LevelProgressStatus::NeedsReview,
                ],
            );

            $this->userCache->forgetProgressRelated($attempt->user_id);

            return $attempt->fresh(['answers', 'quiz', 'level']);
        });
    }

    public function unlockNextLevel(User $user, Level $approvedLevel): void
    {
        $next = Level::query()
            ->where('status', ContentStatus::Published)
            ->where('previous_level_id', $approvedLevel->id)
            ->orderBy('order')
            ->first();

        if ($next === null) {
            $next = Level::query()
                ->where('status', ContentStatus::Published)
                ->where('order', '>', $approvedLevel->order)
                ->orderBy('order')
                ->first();
        }

        if ($next === null) {
            return;
        }

        $progress = LevelProgress::query()->firstOrNew([
            'user_id' => $user->id,
            'level_id' => $next->id,
        ]);

        if (! $progress->exists || $progress->status === LevelProgressStatus::Locked) {
            $progress->status = LevelProgressStatus::Available;
            $progress->save();
        }
    }

    public function assertCoachCanReview(User $coach, QuizAttempt $attempt): void
    {
        if ($coach->id === $attempt->user_id) {
            throw new AuthorizationException('A disciple cannot approve their own level.');
        }

        $relationship = CoachDiscipleRelationship::query()
            ->active()
            ->where('coach_id', $coach->id)
            ->where('disciple_id', $attempt->user_id)
            ->first();

        if ($relationship === null) {
            throw new AuthorizationException('You can only review quiz attempts for your assigned disciples.');
        }

        if (
            $attempt->relationship_id !== null
            && (int) $attempt->relationship_id !== (int) $relationship->id
        ) {
            throw new AuthorizationException('You can only review quiz attempts for your assigned disciples.');
        }
    }

    public function activeRelationshipForDisciple(User $disciple): ?CoachDiscipleRelationship
    {
        return CoachDiscipleRelationship::query()
            ->active()
            ->where('disciple_id', $disciple->id)
            ->latest('id')
            ->first();
    }

    public function activeRelationshipBetween(User $coach, User $disciple): ?CoachDiscipleRelationship
    {
        return CoachDiscipleRelationship::query()
            ->active()
            ->where('coach_id', $coach->id)
            ->where('disciple_id', $disciple->id)
            ->first();
    }

    private function syncLevelProgressAfterLesson(User $user, Level $level): void
    {
        $progress = LevelProgress::query()->firstOrCreate(
            [
                'user_id' => $user->id,
                'level_id' => $level->id,
            ],
            [
                'status' => LevelProgressStatus::Available,
            ],
        );

        if ($progress->status === LevelProgressStatus::Locked) {
            return;
        }

        if (in_array($progress->status, [
            LevelProgressStatus::CoachReview,
            LevelProgressStatus::Approved,
            LevelProgressStatus::NeedsReview,
        ], true)) {
            return;
        }

        $updates = [
            'started_at' => $progress->started_at ?? now(),
        ];

        if ($this->canTakeQuiz($user, $level)) {
            $updates['status'] = LevelProgressStatus::QuizPending;
        } else {
            $updates['status'] = LevelProgressStatus::InProgress;
        }

        $progress->update($updates);
    }
}
