<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PreferredLanguage;
use App\Http\Controllers\Concerns\CachesPublicCurriculumResponses;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\Curriculum\CurriculumService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LessonController extends Controller
{
    use CachesPublicCurriculumResponses;

    public function __construct(private readonly CurriculumService $curriculum) {}

    public function index(Request $request, string $level): JsonResponse
    {
        $language = PreferredLanguage::resolve($request->query('lang'))->value;

        if ($this->curriculum->findPublishedLevel($level) === null) {
            return ApiResponse::error('Level not found.', [], 404);
        }

        return $this->cachedCurriculumSuccess(
            $this->curriculum->getLessonsForLevel($level, $language),
            meta: ['lang' => $language],
        );
    }

    public function show(Request $request, string $lesson): JsonResponse
    {
        $language = PreferredLanguage::resolve($request->query('lang'))->value;
        $payload = $this->curriculum->getLesson($lesson, $language);

        if ($payload === null) {
            return ApiResponse::error('Lesson not found.', [], 404);
        }

        return $this->cachedCurriculumSuccess($payload, meta: ['lang' => $language]);
    }
}
