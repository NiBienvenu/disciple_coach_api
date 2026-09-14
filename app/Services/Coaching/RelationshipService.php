<?php

namespace App\Services\Coaching;

use App\Enums\RelationshipStatus;
use App\Models\CoachDiscipleRelationship;
use App\Models\Note;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RelationshipService
{
    /**
     * @return Collection<int, CoachDiscipleRelationship>
     */
    public function listMine(User $user): Collection
    {
        return CoachDiscipleRelationship::query()
            ->forUser($user)
            ->with(['coach:id,name,email,profile_photo', 'disciple:id,name,email,profile_photo'])
            ->orderByDesc('last_message_at')
            ->orderByDesc('started_at')
            ->get();
    }

    public function show(CoachDiscipleRelationship $relationship, User $viewer): CoachDiscipleRelationship
    {
        $relationship->load([
            'coach:id,name,email,profile_photo',
            'disciple:id,name,email,profile_photo',
            'sessions' => fn ($q) => $q->orderByDesc('scheduled_at'),
            'goals.milestones',
            'notes' => function ($q) use ($viewer, $relationship) {
                $q->orderByDesc('created_at');

                if ((int) $viewer->id === (int) $relationship->disciple_id) {
                    $q->where('is_shared', true);
                }
            },
        ]);

        return $relationship;
    }

    public function end(CoachDiscipleRelationship $relationship, User $actor): CoachDiscipleRelationship
    {
        if (! $relationship->isActive()) {
            throw ValidationException::withMessages([
                'relationship' => ['This relationship is already ended.'],
            ]);
        }

        return DB::transaction(function () use ($relationship) {
            $relationship->update([
                'status' => RelationshipStatus::Ended,
                'ended_at' => now(),
            ]);

            return $relationship->fresh(['coach:id,name,email', 'disciple:id,name,email']);
        });
    }

    /**
     * Notes visible to the viewer for a relationship.
     *
     * @return Collection<int, Note>
     */
    public function visibleNotes(CoachDiscipleRelationship $relationship, User $viewer): Collection
    {
        $query = Note::query()
            ->where('relationship_id', $relationship->id)
            ->orderByDesc('created_at');

        if ((int) $viewer->id === (int) $relationship->disciple_id) {
            $query->where('is_shared', true);
        }

        return $query->get();
    }
}
