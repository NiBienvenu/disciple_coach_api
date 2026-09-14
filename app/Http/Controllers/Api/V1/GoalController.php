<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Coaching\StoreGoalRequest;
use App\Http\Requests\Coaching\UpdateGoalRequest;
use App\Http\Requests\Coaching\UpdateMilestoneRequest;
use App\Http\Resources\Coaching\GoalResource;
use App\Http\Resources\Coaching\MilestoneResource;
use App\Http\Responses\ApiResponse;
use App\Models\CoachDiscipleRelationship;
use App\Models\Goal;
use App\Models\Milestone;
use App\Policies\GoalPolicy;
use App\Services\Coaching\GoalService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class GoalController extends Controller
{
    public function __construct(
        private readonly GoalService $goals,
        private readonly GoalPolicy $policy,
    ) {}

    public function index(Request $request, CoachDiscipleRelationship $relationship): JsonResponse
    {
        if (! $this->policy->viewAny($request->user(), $relationship)) {
            throw new AuthorizationException('You are not a party to this relationship.');
        }

        return ApiResponse::success(
            GoalResource::collection($this->goals->list($relationship))
        );
    }

    public function store(StoreGoalRequest $request, CoachDiscipleRelationship $relationship): JsonResponse
    {
        if (! $this->policy->create($request->user(), $relationship)) {
            throw new AuthorizationException('You are not a party to this relationship.');
        }

        $goal = $this->goals->create($relationship, $request->user(), $request->validated());

        return ApiResponse::success(new GoalResource($goal), status: 201);
    }

    public function update(
        UpdateGoalRequest $request,
        CoachDiscipleRelationship $relationship,
        Goal $goal,
    ): JsonResponse {
        $this->assertGoalBelongs($relationship, $goal);

        if (! $this->policy->update($request->user(), $goal)) {
            throw new AuthorizationException('You are not a party to this relationship.');
        }

        $updated = $this->goals->update($goal, $request->validated());

        return ApiResponse::success(new GoalResource($updated));
    }

    public function destroy(
        Request $request,
        CoachDiscipleRelationship $relationship,
        Goal $goal,
    ): JsonResponse {
        $this->assertGoalBelongs($relationship, $goal);

        if (! $this->policy->delete($request->user(), $goal)) {
            throw new AuthorizationException('You are not a party to this relationship.');
        }

        $this->goals->delete($goal);

        return ApiResponse::success(null, 'Goal deleted.');
    }

    public function updateMilestone(UpdateMilestoneRequest $request, Milestone $milestone): JsonResponse
    {
        $milestone->load('goal');

        if (! $this->policy->updateMilestone($request->user(), $milestone)) {
            throw new AuthorizationException('You are not a party to this relationship.');
        }

        $data = $request->validated();

        if (array_key_exists('title', $data) || array_key_exists('description', $data)) {
            $milestone->update(collect($data)->only(['title', 'description'])->all());
        }

        if (array_key_exists('done', $data)) {
            $updated = $this->goals->toggleMilestone($milestone->fresh(), (bool) $data['done']);
        } else {
            $updated = $milestone->fresh();
        }

        return ApiResponse::success(new MilestoneResource($updated));
    }

    private function assertGoalBelongs(CoachDiscipleRelationship $relationship, Goal $goal): void
    {
        if ((int) $goal->relationship_id !== (int) $relationship->id) {
            throw new NotFoundHttpException('Goal not found for this relationship.');
        }
    }
}
