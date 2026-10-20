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
}
