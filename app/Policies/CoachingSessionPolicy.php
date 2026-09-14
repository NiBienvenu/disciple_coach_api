<?php

namespace App\Policies;

use App\Models\CoachDiscipleRelationship;
use App\Models\CoachingSession;
use App\Models\User;

class CoachingSessionPolicy
{
    public function viewAny(User $user, CoachDiscipleRelationship $relationship): bool
    {
        return $relationship->isParty($user);
    }

    public function create(User $user, CoachDiscipleRelationship $relationship): bool
    {
        return $relationship->isParty($user);
    }

    public function update(User $user, CoachingSession $session): bool
    {
        return (int) $user->id === (int) $session->coach_id
            || (int) $user->id === (int) $session->disciple_id;
    }
}
