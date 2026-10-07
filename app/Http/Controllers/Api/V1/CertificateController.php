<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Certificate;
use App\Services\Certificates\CertificateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CertificateController extends Controller
{
    public function __construct(private readonly CertificateService $certificates) {}

    public function show(Request $request): JsonResponse
    {
        $meta = $this->certificates->getMetaForUser($request->user());

        if ($meta === null) {
            return ApiResponse::error('Certificate not available yet.', [], 404);
        }

        return ApiResponse::success($meta, headers: [
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    public function download(Request $request): BinaryFileResponse|JsonResponse
    {
        $user = $request->user();
        $certificate = Certificate::query()->where('user_id', $user->id)->first();

        if ($certificate === null) {
            return ApiResponse::error('Certificate not available yet.', [], 404);
        }

        $path = $this->certificates->absolutePdfPath($certificate);
        if ($path === null || ! is_file($path)) {
            return ApiResponse::error('Certificate PDF not found.', [], 404);
        }

        return response()->download(
            $path,
            'disciplecoach-certificate-'.$certificate->code.'.pdf',
            ['Content-Type' => 'application/pdf'],
        );
    }
}
