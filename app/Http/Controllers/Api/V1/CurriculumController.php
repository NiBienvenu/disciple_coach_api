<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PreferredLanguage;
use App\Http\Controllers\Concerns\CachesPublicCurriculumResponses;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\Curriculum\CurriculumService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CurriculumController extends Controller
{
    use CachesPublicCurriculumResponses;

    public function __construct(private readonly CurriculumService $curriculum) {}

    public function index(Request $request): JsonResponse
    {
        $language = PreferredLanguage::resolve($request->query('lang'))->value;

        return $this->cachedCurriculumSuccess(
            $this->curriculum->getCurriculum($language),
            meta: ['lang' => $language],
        );
    }
}
