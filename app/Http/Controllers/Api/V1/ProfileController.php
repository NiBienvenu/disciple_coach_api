<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AppRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Requests\Profile\UpdateRolesRequest;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use App\Services\Cache\UserCacheService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function __construct(private readonly UserCacheService $userCache) {}

    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->load('roles');

        return ApiResponse::success(new UserResource($user));
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->fill($request->validated());
        $user->save();

        $this->userCache->forgetProfile($user->id);

        return ApiResponse::success(new UserResource($user->fresh()->load('roles')));
    }

    public function updateRoles(UpdateRolesRequest $request): JsonResponse
    {
        $roles = array_values(array_unique($request->validated('roles')));

        $staffRequested = array_intersect($roles, AppRole::staff());
        if ($staffRequested !== []) {
            throw new AuthorizationException(
                'Staff roles (admin, editor, mentor) cannot be self-assigned.'
            );
        }

        $user = $request->user();

        // Keep any existing staff roles; only replace self-assignable roles.
        $currentStaff = $user->getRoleNames()
            ->intersect(AppRole::staff())
            ->values()
            ->all();

        $user->syncRoles(array_merge($currentStaff, $roles));

        $this->userCache->forgetProfile($user->id);

        return ApiResponse::success(new UserResource($user->fresh()->load('roles')));
    }
}
