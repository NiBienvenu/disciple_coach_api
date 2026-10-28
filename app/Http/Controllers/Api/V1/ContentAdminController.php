<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Lesson;
use App\Models\Level;
use App\Models\QuizQuestion;
use App\Services\Admin\ContentAdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Admin CRUD for parts/levels, lessons and quiz questions (all languages). */
class ContentAdminController extends Controller
{
    public function __construct(private readonly ContentAdminService $content) {}

    public function levels(): JsonResponse
    {
        return ApiResponse::success($this->content->levels());
    }

    public function storeLevel(Request $request): JsonResponse
    {
        $level = $this->content->saveLevel(null, $this->validateLevel($request, creating: true));

        return ApiResponse::success($this->content->levelPayload($level), status: 201);
    }

    public function updateLevel(Request $request, Level $level): JsonResponse
    {
        return ApiResponse::success($this->content->levelPayload($this->content->saveLevel($level, $this->validateLevel($request, creating: false))));
    }

    public function destroyLevel(Level $level): JsonResponse
    {
        $this->content->deleteLevel($level);

        return ApiResponse::success(null, 'Level deleted.');
    }

    public function showLesson(Lesson $lesson): JsonResponse
    {
        return ApiResponse::success($this->content->lesson($lesson));
    }

    public function storeLesson(Request $request, Level $level): JsonResponse
    {
        $lesson = $this->content->saveLesson($level, null, $this->validateLesson($request, creating: true));

        return ApiResponse::success($this->content->lesson($lesson), status: 201);
    }

    public function updateLesson(Request $request, Lesson $lesson): JsonResponse
    {
        $saved = $this->content->saveLesson($lesson->level, $lesson, $this->validateLesson($request, creating: false));

        return ApiResponse::success($this->content->lesson($saved));
    }

    /** Multipart upload, field `image`. Replaces the current image. */
    public function storeLessonImage(Request $request, Lesson $lesson): JsonResponse
    {
        $data = $request->validate([
            'image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        return ApiResponse::success($this->content->lesson($this->content->saveLessonImage($lesson, $data['image'])));
    }

    public function destroyLessonImage(Lesson $lesson): JsonResponse
    {
        return ApiResponse::success($this->content->lesson($this->content->removeLessonImage($lesson)));
    }

    public function destroyLesson(Lesson $lesson): JsonResponse
    {
        $this->content->deleteLesson($lesson);

        return ApiResponse::success(null, 'Lesson deleted.');
    }

    public function quiz(Level $level): JsonResponse
    {
        return ApiResponse::success($this->content->quiz($level));
    }

    public function storeQuestion(Request $request, Level $level): JsonResponse
    {
        $q = $this->content->saveQuestion($level, null, $this->validateQuestion($request, creating: true));

        return ApiResponse::success($this->content->questionPayload($q), status: 201);
    }

    public function updateQuestion(Request $request, QuizQuestion $question): JsonResponse
    {
        $q = $this->content->saveQuestion($question->quiz->level, $question, $this->validateQuestion($request, creating: false));

        return ApiResponse::success($this->content->questionPayload($q));
    }

    public function destroyQuestion(QuizQuestion $question): JsonResponse
    {
        $this->content->deleteQuestion($question);

        return ApiResponse::success(null, 'Question deleted.');
    }

    /** @return array<string, mixed> */
    private function validateLevel(Request $request, bool $creating): array
    {
        $req = $creating ? 'required' : 'sometimes';

        return $request->validate([
            'order' => ['sometimes', 'integer', 'min:0'],
            'part' => ['sometimes', 'integer', 'between:1,9'],
            'previous_level_id' => ['sometimes', 'nullable', 'integer', 'exists:levels,id'],
            'translations' => [$req, 'array:fr,rn,en'],
            'translations.fr.title' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'translations.*' => ['array'],
            'translations.*.title' => ['sometimes', 'string', 'max:255'],
            'translations.*.description' => ['sometimes', 'nullable', 'string'],
            'translations.*.part_title' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);
    }

    /** @return array<string, mixed> */
    private function validateLesson(Request $request, bool $creating): array
    {
        return $request->validate([
            'code' => [$creating ? 'required' : 'sometimes', 'string', 'max:20'],
            'type' => ['sometimes', Rule::in(['main', 'supplementary'])],
            'order' => ['sometimes', 'integer', 'min:0'],
            // youtube.com/watch?v=…, youtu.be/…, /shorts/…, /embed/…, /live/…
            'video_url' => ['sometimes', 'nullable', 'string', 'max:255',
                'regex:~^(https?://)?((www|m)\.)?(youtube\.com/(watch\?(.*&)?v=|embed/|shorts/|live/)|youtu\.be/)[A-Za-z0-9_-]{11}~'],
            'translations' => [$creating ? 'required' : 'sometimes', 'array:fr,rn,en'],
            'translations.fr.title' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'translations.*.title' => ['sometimes', 'string', 'max:255'],
            'translations.*.central_idea' => ['sometimes', 'nullable', 'string'],
            'translations.*.body' => ['sometimes', 'nullable', 'string'],
            'translations.*.objectives' => ['sometimes', 'array'],
            'translations.*.summary_points' => ['sometimes', 'array'],
            'translations.*.reflection_questions' => ['sometimes', 'array'],
            'translations.*.key_scriptures' => ['sometimes', 'array'],
            'translations.*.key_scriptures.*.reference' => ['sometimes', 'nullable', 'string'],
            'translations.*.key_scriptures.*.text' => ['sometimes', 'nullable', 'string'],
        ]);
    }

    /** @return array<string, mixed> */
    private function validateQuestion(Request $request, bool $creating): array
    {
        return $request->validate([
            'order' => ['sometimes', 'integer', 'min:0'],
            'correct_index' => [$creating ? 'required' : 'sometimes', 'integer', 'min:0'],
            'translations' => [$creating ? 'required' : 'sometimes', 'array:fr,rn,en'],
            'translations.fr.prompt' => [$creating ? 'required' : 'sometimes', 'string'],
            'translations.fr.choices' => [$creating ? 'required' : 'sometimes', 'array', 'min:2'],
            'translations.*.prompt' => ['sometimes', 'string'],
            'translations.*.choices' => ['sometimes', 'array', 'min:2'],
        ]);
    }
}
