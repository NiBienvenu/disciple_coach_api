<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Coaching\CoachRequestResource;
use App\Http\Resources\Coaching\RelationshipResource;
use App\Http\Responses\ApiResponse;
use App\Models\CoachRequest;
use App\Services\Coaching\CoachDashboardService;
use App\Services\Coaching\CoachRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CoachRequestController extends Controller
{
    public function __construct(
        private readonly CoachRequestService $requests,
        private readonly CoachDashboardService $dashboard,
    ) {}

    /** Coaches a disciple can pick from. */
    public function coaches(Request $request): JsonResponse
    {
        $coaches = $this->requests->availableCoaches($request->user());

        return ApiResponse::success($coaches->map(fn ($c) => [
            'id' => $c->id,
            'name' => $c->name,
            'email' => $c->email,
            'profile_photo' => $c->profile_photo,
            'preferred_language' => $c->preferred_language instanceof \BackedEnum ? $c->preferred_language->value : $c->preferred_language,
            'active_disciples_count' => (int) $c->active_disciples_count,
        ])->values());
    }

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::success(CoachRequestResource::collection($this->requests->listFor($request->user())));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'coach_id' => ['required', 'integer'],
            'message' => ['nullable', 'string', 'max:500'],
        ]);

        $created = $this->requests->create($request->user(), (int) $data['coach_id'], $data['message'] ?? null);

        return ApiResponse::success(new CoachRequestResource($created), status: 201);
    }

    public function accept(Request $request, CoachRequest $coachRequest): JsonResponse
    {
        $relationship = $this->requests->accept($request->user(), $coachRequest);
        $this->dashboard->forgetDashboard($request->user());

        return ApiResponse::success(new RelationshipResource($relationship), status: 201);
    }

    public function decline(Request $request, CoachRequest $coachRequest): JsonResponse
    {
        return ApiResponse::success(new CoachRequestResource($this->requests->decline($request->user(), $coachRequest)));
    }

    public function cancel(Request $request, CoachRequest $coachRequest): JsonResponse
    {
        return ApiResponse::success(new CoachRequestResource($this->requests->cancel($request->user(), $coachRequest)));
    }
}
