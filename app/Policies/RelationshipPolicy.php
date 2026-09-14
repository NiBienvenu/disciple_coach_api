<?php

namespace App\Policies;

use App\Models\CoachDiscipleRelationship;
use App\Models\User;

class RelationshipPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, CoachDiscipleRelationship $relationship): bool
    {
        return $relationship->isParty($user);
    }

    public function end(User $user, CoachDiscipleRelationship $relationship): bool
    {
        return $relationship->isParty($user);
    }

    public function manage(User $user, CoachDiscipleRelationship $relationship): bool
    {
        return $relationship->isParty($user);
    }

    public function message(User $user, CoachDiscipleRelationship $relationship): bool
    {
        return $relationship->isParty($user);
    }

    public function viewDashboard(User $user): bool
    {
        return $user->can('coaching.manage') || $user->can('progress.coach');
    }
}
