<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AppRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Requests\Profile\UpdateRolesRequest;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Services\Push\PushNotifier;
use App\Services\Cache\UserCacheService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Guard;
use Spatie\Permission\Models\Role;

class ProfileController extends Controller
{
    public function __construct(
        private readonly UserCacheService $userCache,
        private readonly PushNotifier $push,
    ) {}

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

        if (array_intersect($roles, AppRole::staff()) !== []) {
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

        $guard = Guard::getDefaultName($user);
        foreach ($roles as $roleName) {
            Role::findOrCreate($roleName, $guard);
        }

        $becomesCoach = in_array(AppRole::Coach->value, $roles, true) && $user->coach_status !== 'approved';

        $user->syncRoles(array_merge($currentStaff, $roles));

        if ($becomesCoach) {
            // A new coach waits for an admin before coaching anyone.
            $user->forceFill(['coach_status' => 'pending'])->save();
            $admins = User::query()->role(AppRole::Admin->value)->where('is_active', true)->get();
            $this->push->notify($admins, 'Coach à approuver', "{$user->name} demande un compte coach.", [
                'type' => 'coach_pending',
                'user_id' => $user->id,
            ]);
        } elseif (! in_array(AppRole::Coach->value, $roles, true) && $user->coach_status !== null) {
            $user->forceFill(['coach_status' => null])->save();
        }

        $this->userCache->forgetProfile($user->id);

        return ApiResponse::success(new UserResource($user->fresh()->load('roles')));
    }

    /** Any signed-in user (admin included) changes their own password. */
    public function updatePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string', 'current_password:sanctum'],
            'password' => ['required', 'confirmed', \Illuminate\Validation\Rules\Password::defaults(), 'different:current_password'],
        ]);

        $user = $request->user();
        $user->forceFill(['password' => $data['password']])->save();

        // Sign out other devices; keep this session.
        $currentId = $user->currentAccessToken()?->id;
        $user->tokens()->when($currentId, fn ($q) => $q->where('id', '!=', $currentId))->delete();

        return ApiResponse::success(null, 'Password updated.');
    }
}
