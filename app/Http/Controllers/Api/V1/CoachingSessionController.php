<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Coaching\StoreSessionRequest;
use App\Http\Requests\Coaching\UpdateSessionRequest;
use App\Http\Resources\Coaching\CoachingSessionResource;
use App\Http\Responses\ApiResponse;
use App\Models\CoachDiscipleRelationship;
use App\Models\CoachingSession;
use App\Policies\CoachingSessionPolicy;
use App\Services\Coaching\CoachDashboardService;
use App\Services\Coaching\SessionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CoachingSessionController extends Controller
{
    public function __construct(
        private readonly SessionService $sessions,
        private readonly CoachingSessionPolicy $policy,
        private readonly CoachDashboardService $dashboard,
    ) {}

    public function index(Request $request, CoachDiscipleRelationship $relationship): JsonResponse
    {
        if (! $this->policy->viewAny($request->user(), $relationship)) {
            throw new AuthorizationException('You are not a party to this relationship.');
        }

        return ApiResponse::success(
            CoachingSessionResource::collection($this->sessions->list($relationship))
        );
    }

    public function store(StoreSessionRequest $request, CoachDiscipleRelationship $relationship): JsonResponse
    {
        if (! $this->policy->create($request->user(), $relationship)) {
            throw new AuthorizationException('You are not a party to this relationship.');
        }

        $session = $this->sessions->create($relationship, $request->user(), $request->validated());
        $this->dashboard->forgetDashboard($relationship->coach);

        return ApiResponse::success(new CoachingSessionResource($session), status: 201);
    }

    public function update(
        UpdateSessionRequest $request,
        CoachDiscipleRelationship $relationship,
        CoachingSession $session,
    ): JsonResponse {
        $this->assertSessionBelongs($relationship, $session);

        if (! $this->policy->update($request->user(), $session)) {
            throw new AuthorizationException('You are not a party to this relationship.');
        }

        $updated = $this->sessions->update($session, $request->validated());
        $this->dashboard->forgetDashboard($relationship->coach);

        return ApiResponse::success(new CoachingSessionResource($updated));
    }

    private function assertSessionBelongs(CoachDiscipleRelationship $relationship, CoachingSession $session): void
    {
        if ((int) $session->relationship_id !== (int) $relationship->id) {
            throw new NotFoundHttpException('Session not found for this relationship.');
        }
    }
}
