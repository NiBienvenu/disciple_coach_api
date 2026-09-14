<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Coaching\StoreMessageRequest;
use App\Http\Resources\Coaching\RelationshipMessageResource;
use App\Http\Resources\Coaching\RelationshipResource;
use App\Http\Responses\ApiResponse;
use App\Models\CoachDiscipleRelationship;
use App\Policies\RelationshipMessagePolicy;
use App\Services\Coaching\MessagingService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RelationshipMessageController extends Controller
{
    public function __construct(
        private readonly MessagingService $messaging,
        private readonly RelationshipMessagePolicy $policy,
    ) {}

    public function index(Request $request, CoachDiscipleRelationship $relationship): JsonResponse
    {
        if (! $this->policy->viewAny($request->user(), $relationship)) {
            throw new AuthorizationException('You are not a party to this relationship.');
        }

        $result = $this->messaging->list(
            $relationship,
            $request->user(),
            $request->query('cursor'),
        );

        /** @var \Illuminate\Pagination\CursorPaginator $paginator */
        $paginator = $result['messages'];

        return ApiResponse::success(
            RelationshipMessageResource::collection($paginator->items()),
            meta: [
                'unread_count' => $result['unread_count'],
                'next_cursor' => $paginator->nextCursor()?->encode(),
                'prev_cursor' => $paginator->previousCursor()?->encode(),
                'per_page' => $paginator->perPage(),
                'has_more' => $paginator->hasMorePages(),
            ],
        );
    }

    public function store(StoreMessageRequest $request, CoachDiscipleRelationship $relationship): JsonResponse
    {
        if (! $this->policy->create($request->user(), $relationship)) {
            throw new AuthorizationException('You are not a party to this relationship.');
        }

        $message = $this->messaging->send(
            $relationship,
            $request->user(),
            $request->validated('body'),
        );

        return ApiResponse::success(new RelationshipMessageResource($message), status: 201);
    }

    public function markRead(Request $request, CoachDiscipleRelationship $relationship): JsonResponse
    {
        if (! $this->policy->viewAny($request->user(), $relationship)) {
            throw new AuthorizationException('You are not a party to this relationship.');
        }

        $updated = $this->messaging->markRead($relationship, $request->user());

        return ApiResponse::success(new RelationshipResource($updated));
    }
}
