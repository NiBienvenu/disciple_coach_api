<?php

namespace App\Http\Controllers\Concerns;

use App\Http\Responses\ApiResponse;
use App\Services\Cache\CacheKey;
use Illuminate\Http\JsonResponse;

trait CachesPublicCurriculumResponses
{
    /**
     * Curriculum responses carry an ETag. Clients may cache them but must
     * revalidate, so a content change is seen on the very next request
     * (a 304 costs almost nothing when nothing changed).
     */
    protected function cachedCurriculumSuccess(
        mixed $data,
        array $meta = [],
        int $maxAgeSeconds = 0,
    ): JsonResponse {
        $meta['revision'] = CacheKey::revision();
        $response = ApiResponse::success($data, meta: $meta);

        $etag = '"'.md5((string) $response->getContent()).'"';
        $response->headers->set('Cache-Control', "public, max-age={$maxAgeSeconds}, must-revalidate");
        $response->headers->set('ETag', $etag);

        $request = request();
        if (in_array($etag, $request->getETags(), true)) {
            $response->setNotModified();
        }

        return $response;
    }
}
