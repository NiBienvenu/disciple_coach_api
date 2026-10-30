<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PreferredLanguage;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Lesson;
use App\Models\LessonPage;
use App\Models\LessonQuizAttempt;
use App\Models\User;
use App\Policies\QuizAttemptPolicy;
use App\Services\Progress\LessonQuizService;
use App\Services\Progress\LevelProgressionService;
use App\Services\Push\PushNotifier;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Answers to the end-of-lesson questions, and the coach's review of them. */
class LessonQuizAttemptController extends Controller
{
    public function __construct(
        private readonly LessonQuizService $quizzes,
        private readonly LevelProgressionService $progression,
        private readonly QuizAttemptPolicy $policy,
        private readonly PushNotifier $push,
    ) {}

    public function store(Request $request, Lesson $lesson): JsonResponse
    {
        $data = $request->validate([
            'answers' => ['required', 'array', 'min:1'],
            'answers.*.page_id' => ['required', 'integer'],
            'answers.*.selected_index' => ['required', 'integer', 'min:0'],
        ]);
        $user = $request->user();
        $attempt = $this->quizzes->submit($user, $lesson, $data['answers']);

        $relationship = $this->progression->activeRelationshipForDisciple($user);
        if ($attempt->status === LessonQuizAttempt::PENDING && $relationship !== null) {
            $this->push->notify(
                $relationship->coach,
                ['fr' => 'Réponses à valider', 'en' => 'Answers to review'],
                ['fr' => "{$user->name} a terminé la leçon {$lesson->code}.", 'en' => "{$user->name} finished lesson {$lesson->code}."],
                [
                    'type' => 'lesson_quiz_submitted',
                    'attempt_id' => $attempt->id,
                    'disciple_id' => $user->id,
                    'relationship_id' => $relationship->id,
                ],
            );
        }

        return ApiResponse::success($this->payload($request, $attempt), status: 201);
    }

    public function mine(Request $request): JsonResponse
    {
        return ApiResponse::success($this->quizzes->attemptsFor($request->user(), $this->language($request)));
    }

    public function forDisciple(Request $request, User $user): JsonResponse
    {
        if (! $this->policy->viewDisciple($request->user(), $user)) {
            throw new AuthorizationException('You can only view answers of your assigned disciples.');
        }

        return ApiResponse::success($this->quizzes->attemptsFor($user, $this->language($request)));
    }

    /** Body: `decision` approve|reset, optional `feedback` shown to the disciple. */
    public function decide(Request $request, LessonQuizAttempt $attempt): JsonResponse
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reset'])],
            'feedback' => ['nullable', 'string', 'max:5000'],
        ]);
        $feedback = trim((string) ($data['feedback'] ?? '')) ?: null;
        $updated = $this->quizzes->decide($request->user(), $attempt, $data['decision'], $feedback);

        $approved = $data['decision'] === 'approve';
        $code = $attempt->lesson?->code;
        $this->push->notify(
            $attempt->user,
            $approved
                ? ['fr' => 'Leçon validée 🎉', 'en' => 'Lesson approved 🎉']
                : ['fr' => 'Votre coach vous demande de refaire les questions', 'en' => 'Your coach asks you to answer again'],
            $feedback ?? ($approved
                ? ['fr' => "La leçon {$code} est validée. La suite est ouverte.", 'en' => "Lesson {$code} is approved. The next one is open."]
                : ['fr' => "Relisez la leçon {$code}, puis renvoyez vos réponses.", 'en' => "Read lesson {$code} again, then send your answers."]),
            ['type' => 'lesson_quiz_decided', 'attempt_id' => $attempt->id, 'lesson_id' => $attempt->lesson_id, 'approved' => $approved ? '1' : '0'],
        );

        return ApiResponse::success($this->payload($request, $updated));
    }

    /** @return array<string, mixed> */
    private function payload(Request $request, LessonQuizAttempt $attempt): array
    {
        $attempt->loadMissing('lesson:id,level_id,code,order');
        $pages = LessonPage::query()
            ->where('lesson_id', $attempt->lesson_id)
            ->where('kind', LessonPage::KIND_QUESTION)
            ->with('translations')
            ->get()
            ->keyBy('id');

        return $this->quizzes->payload($attempt, $pages, $this->language($request));
    }

    private function language(Request $request): string
    {
        return PreferredLanguage::resolve($request->query('lang'))->value;
    }
}
