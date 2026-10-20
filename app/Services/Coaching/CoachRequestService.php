<?php

namespace App\Services\Coaching;

use App\Enums\RelationshipStatus;
use App\Models\CoachDiscipleRelationship;
use App\Models\CoachRequest;
use App\Models\User;
use App\Services\Push\PushNotifier;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CoachRequestService
{
    public function __construct(private readonly PushNotifier $push) {}

    /**
     * Approved, active coaches a disciple can ask for coaching.
     *
     * @return Collection<int, User>
     */
    public function availableCoaches(User $viewer): Collection
    {
        return User::query()
            ->role('coach')
            ->where('is_active', true)
            ->where('coach_status', 'approved')
            ->where('id', '!=', $viewer->id)
            ->withCount([
                'coachRelationships as active_disciples_count' => fn ($q) => $q->where('status', RelationshipStatus::Active),
            ])
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'profile_photo', 'preferred_language']);
    }

    /**
     * @return Collection<int, CoachRequest>
     */
    public function listFor(User $user): Collection
    {
        return CoachRequest::query()
            ->where(fn ($q) => $q->where('coach_id', $user->id)->orWhere('disciple_id', $user->id))
            ->with(['coach:id,name,email,profile_photo', 'disciple:id,name,email,profile_photo'])
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();
    }

    public function create(User $disciple, int $coachId, ?string $message): CoachRequest
    {
        $coach = User::query()->find($coachId);
        if ($coach === null || ! $coach->isApprovedCoach() || $coach->id === $disciple->id) {
            throw ValidationException::withMessages(['coach_id' => ['This coach is not available.']]);
        }

        if (CoachDiscipleRelationship::query()->active()->where('disciple_id', $disciple->id)->exists()) {
            throw ValidationException::withMessages(['coach_id' => ['You already have a coach.']]);
        }

        if (CoachRequest::query()->where('disciple_id', $disciple->id)->where('status', CoachRequest::PENDING)->exists()) {
            throw ValidationException::withMessages(['coach_id' => ['You already have a pending request. Cancel it first.']]);
        }

        $request = CoachRequest::query()->create([
            'disciple_id' => $disciple->id,
            'coach_id' => $coach->id,
            'status' => CoachRequest::PENDING,
            'message' => $message,
        ]);

        $this->push->notify($coach, ['fr' => 'Nouvelle demande de coaching', 'en' => 'New coaching request'], ['fr' => "{$disciple->name} souhaite que vous soyez son coach.", 'en' => "{$disciple->name} would like you to be their coach."], [
            'type' => 'coach_request',
            'request_id' => $request->id,
        ]);

        return $request->load(['coach:id,name,email,profile_photo', 'disciple:id,name,email,profile_photo']);
    }

    public function accept(User $coach, CoachRequest $request): CoachDiscipleRelationship
    {
        $this->assertPendingFor($coach, $request, 'coach_id');
        if (! $coach->isApprovedCoach()) {
            throw ValidationException::withMessages(['request' => ['Your coach account is not approved yet.']]);
        }

        $relationship = DB::transaction(function () use ($coach, $request) {
            $hasCoach = CoachDiscipleRelationship::query()->active()
                ->where('disciple_id', $request->disciple_id)
                ->lockForUpdate()
                ->exists();
            if ($hasCoach) {
                $request->update(['status' => CoachRequest::CANCELLED, 'responded_at' => now()]);
                throw ValidationException::withMessages(['request' => ['This disciple already has a coach.']]);
            }

            $request->update(['status' => CoachRequest::ACCEPTED, 'responded_at' => now()]);

            return CoachDiscipleRelationship::query()->create([
                'coach_id' => $coach->id,
                'disciple_id' => $request->disciple_id,
                'status' => RelationshipStatus::Active,
                'started_at' => now(),
                'last_read_at' => [
                    (string) $coach->id => now()->toIso8601String(),
                    (string) $request->disciple_id => now()->toIso8601String(),
                ],
            ]);
        });

        $this->push->notify($request->disciple, ['fr' => 'Demande acceptée', 'en' => 'Request accepted'], ['fr' => "{$coach->name} est maintenant votre coach.", 'en' => "{$coach->name} is now your coach."], [
            'type' => 'coach_request_accepted',
            'relationship_id' => $relationship->id,
        ]);

        return $relationship->load(['coach:id,name,email,profile_photo', 'disciple:id,name,email,profile_photo']);
    }

    public function decline(User $coach, CoachRequest $request): CoachRequest
    {
        $this->assertPendingFor($coach, $request, 'coach_id');
        $request->update(['status' => CoachRequest::DECLINED, 'responded_at' => now()]);

        $this->push->notify($request->disciple, ['fr' => 'Demande refusée', 'en' => 'Request declined'], ['fr' => "{$coach->name} ne peut pas vous accompagner pour le moment. Choisissez un autre coach.", 'en' => "{$coach->name} can't coach you right now. Please choose another coach."], [
            'type' => 'coach_request_declined',
            'request_id' => $request->id,
        ]);

        return $request->load(['coach:id,name,email,profile_photo', 'disciple:id,name,email,profile_photo']);
    }

    public function cancel(User $disciple, CoachRequest $request): CoachRequest
    {
        $this->assertPendingFor($disciple, $request, 'disciple_id');
        $request->update(['status' => CoachRequest::CANCELLED, 'responded_at' => now()]);

        return $request->load(['coach:id,name,email,profile_photo', 'disciple:id,name,email,profile_photo']);
    }

    private function assertPendingFor(User $user, CoachRequest $request, string $column): void
    {
        if ((int) $request->{$column} !== (int) $user->id) {
            throw new \Illuminate\Auth\Access\AuthorizationException('This request is not yours.');
        }
        if ($request->status !== CoachRequest::PENDING) {
            throw ValidationException::withMessages(['request' => ['This request has already been answered.']]);
        }
    }
}
