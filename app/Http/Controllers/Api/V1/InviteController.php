<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Coaching\RedeemInviteRequest;
use App\Http\Resources\Coaching\InviteResource;
use App\Http\Resources\Coaching\RelationshipResource;
use App\Http\Responses\ApiResponse;
use App\Policies\InvitePolicy;
use App\Services\Coaching\CoachDashboardService;
use App\Services\Coaching\InviteService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InviteController extends Controller
{
    public function __construct(
        private readonly InviteService $invites,
        private readonly InvitePolicy $policy,
        private readonly CoachDashboardService $dashboard,
    ) {}

    public function store(Request $request): JsonResponse
    {
        if (! $this->policy->create($request->user())) {
            throw new AuthorizationException('Only coaches can create invite codes.');
        }

        $invite = $this->invites->create($request->user());

        return ApiResponse::success(new InviteResource($invite), status: 201);
    }

    public function redeem(RedeemInviteRequest $request, string $code): JsonResponse
    {
        if (! $this->policy->redeem($request->user())) {
            throw new AuthorizationException('You are not allowed to redeem invites.');
        }

        $relationship = $this->invites->redeem($request->user(), $code);
        $relationship->load(['coach:id,name,email,profile_photo', 'disciple:id,name,email,profile_photo']);

        $this->dashboard->forgetDashboard($relationship->coach);

        return ApiResponse::success(new RelationshipResource($relationship), status: 201);
    }
}
