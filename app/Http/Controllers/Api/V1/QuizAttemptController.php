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
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuizAttemptController extends Controller
{
    public function __construct(
        private readonly LevelProgressionService $progression,
        private readonly DashboardService $dashboard,
        private readonly QuizAttemptPolicy $policy,
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
