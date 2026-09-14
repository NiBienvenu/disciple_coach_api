<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Coaching\RelationshipResource;
use App\Http\Responses\ApiResponse;
use App\Models\CoachDiscipleRelationship;
use App\Policies\RelationshipPolicy;
use App\Services\Coaching\CoachDashboardService;
use App\Services\Coaching\RelationshipService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RelationshipController extends Controller
{
    public function __construct(
        private readonly RelationshipService $relationships,
        private readonly RelationshipPolicy $policy,
        private readonly CoachDashboardService $dashboard,
    ) {}

    public function index(Request $request): JsonResponse
    {
        if (! $this->policy->viewAny($request->user())) {
            throw new AuthorizationException;
        }

        $items = $this->relationships->listMine($request->user());

        return ApiResponse::success(RelationshipResource::collection($items));
    }

    public function show(Request $request, CoachDiscipleRelationship $relationship): JsonResponse
    {
        if (! $this->policy->view($request->user(), $relationship)) {
            throw new AuthorizationException('You are not a party to this relationship.');
        }

        $detailed = $this->relationships->show($relationship, $request->user());

        return ApiResponse::success(new RelationshipResource($detailed));
    }

    public function end(Request $request, CoachDiscipleRelationship $relationship): JsonResponse
    {
        if (! $this->policy->end($request->user(), $relationship)) {
            throw new AuthorizationException('You are not a party to this relationship.');
        }

        $ended = $this->relationships->end($relationship, $request->user());
        $this->dashboard->forgetDashboard($ended->coach);

        return ApiResponse::success(new RelationshipResource($ended));
    }
}
