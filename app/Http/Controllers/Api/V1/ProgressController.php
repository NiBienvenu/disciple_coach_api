<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PreferredLanguage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Progress\CompleteLessonRequest;
use App\Http\Resources\Progress\LessonProgressResource;
use App\Http\Responses\ApiResponse;
use App\Models\Lesson;
use App\Services\Progress\DashboardService;
use App\Services\Progress\LevelProgressionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProgressController extends Controller
{
    public function __construct(
        private readonly LevelProgressionService $progression,
        private readonly DashboardService $dashboard,
    ) {}

    public function progress(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->dashboard->getLessonProgress($request->user())
        );
    }

    public function completeLesson(CompleteLessonRequest $request, Lesson $lesson): JsonResponse
    {
        $progress = $this->progression->completeLesson(
            $request->user(),
            $lesson,
            $request->status(),
        );

        return ApiResponse::success(new LessonProgressResource($progress->load('lesson')));
    }

    public function levelProgress(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->dashboard->getLevelProgress($request->user())
        );
    }

    public function dashboard(Request $request): JsonResponse
    {
        $language = PreferredLanguage::resolve($request->query('lang'))->value;

        return ApiResponse::success(
            $this->dashboard->getDashboard($request->user(), $language),
            meta: ['lang' => $language],
        );
    }
}
