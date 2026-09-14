<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Policies\RelationshipPolicy;
use App\Services\Coaching\CoachDashboardService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CoachDashboardController extends Controller
{
    public function __construct(
        private readonly CoachDashboardService $dashboard,
        private readonly RelationshipPolicy $policy,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        if (! $this->policy->viewDashboard($request->user())) {
            throw new AuthorizationException('Only coaches can view the coaching dashboard.');
        }

        return ApiResponse::success($this->dashboard->getDashboard($request->user()));
    }
}
