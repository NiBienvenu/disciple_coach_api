<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PreferredLanguage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Progress\DecideQuizAttemptRequest;
use App\Http\Requests\Progress\SubmitQuizRequest;
use App\Http\Resources\Progress\QuizAttemptResource;
use App\Http\Responses\ApiResponse;
use App\Models\Level;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Policies\QuizAttemptPolicy;
use App\Services\Progress\DashboardService;
use App\Services\Progress\LevelProgressionService;
use App\Services\Push\PushNotifier;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuizAttemptController extends Controller
{
    public function __construct(
        private readonly LevelProgressionService $progression,
        private readonly DashboardService $dashboard,
        private readonly QuizAttemptPolicy $policy,
        private readonly PushNotifier $push,
    ) {}

    public function store(SubmitQuizRequest $request, Level $level): JsonResponse
    {
        if (! $this->policy->create($request->user())) {
            throw new AuthorizationException('You are not allowed to submit quizzes.');
        }

        $attempt = $this->progression->submitQuiz(
            $request->user(),
            $level,
            $request->answers(),
        );

        $relationship = $this->progression->activeRelationshipForDisciple($request->user());
        if ($relationship !== null) {
            $this->push->notify($relationship->coach, ['fr' => 'Quiz à corriger', 'en' => 'Quiz to review'], ['fr' => "{$request->user()->name} a terminé un quiz de niveau.", 'en' => "{$request->user()->name} finished a level quiz."], [
                'type' => 'quiz_submitted',
                'attempt_id' => $attempt->id,
                'disciple_id' => $request->user()->id,
                'relationship_id' => $relationship->id,
            ]);
        }

        return ApiResponse::success(
            new QuizAttemptResource($attempt->load(['answers', 'level'])),
            status: 201,
        );
    }

    public function mine(Request $request): JsonResponse
    {
        $language = PreferredLanguage::resolve($request->query('lang'))->value;

        return ApiResponse::success(
            $this->dashboard->getQuizAttemptsForUser($request->user(), false, $language),
            meta: ['lang' => $language],
        );
    }

    public function forDisciple(Request $request, User $user): JsonResponse
    {
        if (! $this->policy->viewDisciple($request->user(), $user)) {
            throw new AuthorizationException('You can only view quiz attempts for your assigned disciples.');
        }

        $language = PreferredLanguage::resolve($request->query('lang'))->value;

        return ApiResponse::success(
            $this->dashboard->getQuizAttemptsForUser($user, true, $language),
            meta: ['lang' => $language],
        );
    }

    public function discipleProgress(Request $request, User $user): JsonResponse
    {
        if (! $this->policy->viewDisciple($request->user(), $user)) {
            throw new AuthorizationException('You can only view progress for your assigned disciples.');
        }

        return ApiResponse::success([
            'lesson_progress' => $this->dashboard->getLessonProgress($user),
            'level_progress' => $this->dashboard->getLevelProgress($user),
        ]);
    }

    public function decide(DecideQuizAttemptRequest $request, QuizAttempt $attempt): JsonResponse
    {
        $coach = $request->user();

        if (! $this->policy->decide($coach, $attempt)) {
            throw new AuthorizationException(
                $coach->id === $attempt->user_id
                    ? 'A disciple cannot approve their own level.'
                    : 'You can only review quiz attempts for your assigned disciples.'
            );
        }

        $updated = $request->decision() === 'approve'
            ? $this->progression->approveLevel($coach, $attempt, $request->feedback())
            : $this->progression->rejectLevel($coach, $attempt, $request->feedback());

        $approved = $request->decision() === 'approve';
        $this->push->notify(
            User::query()->find($attempt->user_id),
            $approved ? ['fr' => 'Niveau validé 🎉', 'en' => 'Level approved 🎉'] : ['fr' => 'Votre coach demande une révision', 'en' => 'Your coach asks for a review'],
            $approved
                ? ['fr' => 'Le niveau suivant est débloqué.', 'en' => 'The next level is unlocked.']
                : ($request->feedback() ?: ['fr' => 'Revoyez ce niveau puis refaites le quiz.', 'en' => 'Review this level, then retake the quiz.']),
            ['type' => 'quiz_decided', 'attempt_id' => $attempt->id, 'approved' => $approved ? '1' : '0'],
        );

        $language = PreferredLanguage::resolve($request->query('lang'))->value;

        return ApiResponse::success(
            new QuizAttemptResource(
                $updated->load(['answers.question.translations', 'level']),
                forCoachReview: true,
                language: $language,
            )
        );
    }
}
