<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;

class ApiResponse
{
    public static function success(
        mixed $data = null,
        ?string $message = null,
        array $meta = [],
        int $status = 200,
        array $headers = [],
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => $message,
            'meta' => $meta === [] ? (object) [] : $meta,
        ], $status, $headers);
    }

    /**
     * Public curriculum / quiz responses — browser & CDN friendly.
     *
     * @return array<string, string>
     */
    public static function publicCurriculumHeaders(int $maxAgeSeconds = 3600): array
    {
        return [
            'Cache-Control' => "public, max-age={$maxAgeSeconds}, stale-while-revalidate=86400",
        ];
    }

    public static function error(
        string $message,
        array $errors = [],
        int $status = 400,
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors === [] ? (object) [] : $errors,
        ], $status);
    }
}
