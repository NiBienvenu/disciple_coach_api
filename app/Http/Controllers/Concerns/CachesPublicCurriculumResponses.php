<?php

namespace App\Http\Controllers\Concerns;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

trait CachesPublicCurriculumResponses
{
    protected function cachedCurriculumSuccess(
        mixed $data,
        array $meta = [],
        int $maxAgeSeconds = 3600,
    ): JsonResponse {
        return ApiResponse::success(
            $data,
            meta: $meta,
            headers: ApiResponse::publicCurriculumHeaders($maxAgeSeconds),
        );
    }
}
