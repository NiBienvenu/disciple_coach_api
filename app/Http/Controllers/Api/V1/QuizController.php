<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PreferredLanguage;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\Curriculum\CurriculumService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuizController extends Controller
{
    public function __construct(private readonly CurriculumService $curriculum) {}

    /**
     * Public quiz read — prompt + choices only (no correct_index).
     */
    public function show(Request $request, string $level): JsonResponse
    {
        $language = PreferredLanguage::resolve($request->query('lang'))->value;
        $payload = $this->curriculum->getQuizForLevel($level, $language);

        if ($payload === null) {
            return ApiResponse::error('Quiz not found.', [], 404);
        }

        return ApiResponse::success($payload, meta: ['lang' => $language]);
    }
}
