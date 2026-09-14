<?php

namespace App\Services\Coaching;

use App\Enums\MentorReviewStatus;
use App\Enums\RelationshipStatus;
use App\Enums\SessionStatus;
use App\Models\CoachDiscipleRelationship;
use App\Models\CoachingSession;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Services\Cache\UserCacheService;
use Illuminate\Support\Collection;

class CoachDashboardService
{
    public function __construct(
        private readonly UserCacheService $userCache,
    ) {}

    /**
     * @return array{
     *     assigned_disciples: list<array<string, mixed>>,
     *     pending_reviews: list<array<string, mixed>>,
     *     upcoming_sessions: list<array<string, mixed>>
     * }
     */
    public function getDashboard(User $coach): array
    {
        return $this->userCache->rememberDashboard('coach:'.$coach->id, function () use ($coach) {
            $relationships = CoachDiscipleRelationship::query()
                ->where('coach_id', $coach->id)
                ->where('status', RelationshipStatus::Active)
                ->with(['disciple:id,name,email,profile_photo,preferred_language'])
                ->orderByDesc('started_at')
                ->get();

            $discipleIds = $relationships->pluck('disciple_id')->all();
            $relationshipIds = $relationships->pluck('id')->all();

            $pendingByDisciple = QuizAttempt::query()
                ->whereIn('user_id', $discipleIds !== [] ? $discipleIds : [0])
                ->where('mentor_status', MentorReviewStatus::Pending)
                ->with(['level:id,slug,order', 'user:id,name,email'])
                ->orderByDesc('submitted_at')
                ->get()
                ->groupBy('user_id');

            $upcomingSessions = CoachingSession::query()
                ->whereIn('relationship_id', $relationshipIds !== [] ? $relationshipIds : [0])
                ->where('status', SessionStatus::Scheduled)
                ->where('scheduled_at', '>=', now())
                ->with(['disciple:id,name,email', 'relationship:id,coach_id,disciple_id'])
                ->orderBy('scheduled_at')
                ->limit(20)
                ->get();

            $assigned = $relationships->map(function (CoachDiscipleRelationship $rel) use ($pendingByDisciple) {
                /** @var Collection<int, QuizAttempt> $pending */
                $pending = $pendingByDisciple->get($rel->disciple_id, collect());

                return [
                    'relationship_id' => $rel->id,
                    'status' => $rel->status instanceof \BackedEnum ? $rel->status->value : $rel->status,
                    'started_at' => $rel->started_at?->toIso8601String(),
                    'last_message_at' => $rel->last_message_at?->toIso8601String(),
                    'disciple' => $rel->disciple === null ? null : [
                        'id' => $rel->disciple->id,
                        'name' => $rel->disciple->name,
                        'email' => $rel->disciple->email,
                        'profile_photo' => $rel->disciple->profile_photo,
                        'preferred_language' => $rel->disciple->preferred_language instanceof \BackedEnum
                            ? $rel->disciple->preferred_language->value
                            : $rel->disciple->preferred_language,
                    ],
                    'pending_review_count' => $pending->count(),
                ];
            })->values()->all();

            $pendingReviews = $pendingByDisciple
                ->flatten(1)
                ->map(fn (QuizAttempt $attempt) => [
                    'id' => $attempt->id,
                    'user_id' => $attempt->user_id,
                    'level_id' => $attempt->level_id,
                    'score' => $attempt->score !== null ? (float) $attempt->score : null,
                    'mentor_status' => $attempt->mentor_status instanceof \BackedEnum
                        ? $attempt->mentor_status->value
                        : $attempt->mentor_status,
                    'submitted_at' => $attempt->submitted_at?->toIso8601String(),
                    'disciple' => $attempt->user === null ? null : [
                        'id' => $attempt->user->id,
                        'name' => $attempt->user->name,
                        'email' => $attempt->user->email,
                    ],
                    'level' => $attempt->level === null ? null : [
                        'id' => $attempt->level->id,
                        'slug' => $attempt->level->slug,
                        'order' => $attempt->level->order,
                    ],
                ])
                ->values()
                ->all();

            $sessions = $upcomingSessions->map(fn (CoachingSession $session) => [
                'id' => $session->id,
                'relationship_id' => $session->relationship_id,
                'title' => $session->title,
                'scheduled_at' => $session->scheduled_at?->toIso8601String(),
                'duration_min' => $session->duration_min,
                'status' => $session->status instanceof \BackedEnum ? $session->status->value : $session->status,
                'meeting_link' => $session->meeting_link,
                'disciple' => $session->disciple === null ? null : [
                    'id' => $session->disciple->id,
                    'name' => $session->disciple->name,
                    'email' => $session->disciple->email,
                ],
            ])->values()->all();

            return [
                'assigned_disciples' => $assigned,
                'pending_reviews' => $pendingReviews,
                'upcoming_sessions' => $sessions,
            ];
        });
    }

    public function forgetDashboard(User $coach): void
    {
        $this->userCache->forgetDashboard('coach:'.$coach->id);
    }
}
