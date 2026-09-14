<?php

namespace App\Policies;

use App\Models\CoachDiscipleRelationship;
use App\Models\Note;
use App\Models\User;

class NotePolicy
{
    public function view(User $user, Note $note): bool
    {
        if ((int) $user->id === (int) $note->author_id) {
            return true;
        }

        if (! $note->is_shared) {
            // Private notes are never visible to the disciple (or anyone else).
            return false;
        }

        if ((int) $user->id === (int) $note->disciple_id) {
            return true;
        }

        if ($note->relationship_id !== null) {
            return CoachDiscipleRelationship::query()
                ->whereKey($note->relationship_id)
                ->where(function ($q) use ($user) {
                    $q->where('coach_id', $user->id)
                        ->orWhere('disciple_id', $user->id);
                })
                ->exists();
        }

        return false;
    }

    public function create(User $user, CoachDiscipleRelationship $relationship): bool
    {
        return $relationship->isParty($user);
    }
}
