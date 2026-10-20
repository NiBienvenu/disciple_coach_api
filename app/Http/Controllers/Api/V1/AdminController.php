<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Services\Admin\AdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Admin-only: coach approvals, account activation, statistics. */
class AdminController extends Controller
{
    public function __construct(private readonly AdminService $admin) {}

    public function users(Request $request): JsonResponse
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'filter' => ['nullable', 'string', 'in:all,disciple,coach,admin,pending,disabled'],
        ]);

        $page = $this->admin->listUsers($data['search'] ?? null, $data['filter'] ?? 'all');

        return ApiResponse::success(
            UserResource::collection($page->items()),
            meta: ['total' => $page->total(), 'current_page' => $page->currentPage(), 'last_page' => $page->lastPage()],
        );
    }

    public function setActive(Request $request, User $user): JsonResponse
    {
        $data = $request->validate(['active' => ['required', 'boolean']]);

        return ApiResponse::success(new UserResource($this->admin->setActive($request->user(), $user, (bool) $data['active'])));
    }

    public function setCoachStatus(Request $request, User $user): JsonResponse
    {
        $data = $request->validate(['status' => ['required', 'string', 'in:approved,pending,rejected']]);

        return ApiResponse::success(new UserResource($this->admin->setCoachStatus($user, $data['status'])));
    }

    public function stats(): JsonResponse
    {
        return ApiResponse::success($this->admin->stats());
    }

    /** Is push set up on this server, and how many devices are registered? */
    public function pushStatus(Request $request, \App\Services\Push\FcmService $fcm): JsonResponse
    {
        return ApiResponse::success([
            'configured' => $fcm->isConfigured(),
            'credentials_path' => config('services.fcm.credentials'),
            'devices_total' => \App\Models\DeviceToken::query()->count(),
            'my_devices' => $request->user()->deviceTokens()->count(),
        ]);
    }

    /** Sends a test notification to the admin's own devices, right now. */
    public function pushTest(Request $request, \App\Services\Push\FcmService $fcm): JsonResponse
    {
        if (! $fcm->isConfigured()) {
            return ApiResponse::error('Push is not configured: Firebase key file not found on the server.', [], 422);
        }

        $results = [];
        foreach ($request->user()->deviceTokens()->pluck('token') as $token) {
            $results[] = ['token' => substr($token, 0, 12).'…', 'sent' => $fcm->sendToToken($token, 'DiscipleCoach', 'Test notification ✅', ['type' => 'test'])];
        }

        return ApiResponse::success(['devices' => count($results), 'results' => $results]);
    }
}
