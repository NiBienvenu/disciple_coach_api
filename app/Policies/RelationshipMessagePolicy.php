<?php

namespace App\Policies;

use App\Models\CoachDiscipleRelationship;
use App\Models\Note;
use App\Models\RelationshipMessage;
use App\Models\User;

class RelationshipMessagePolicy
{
    public function viewAny(User $user, CoachDiscipleRelationship $relationship): bool
    {
        return $relationship->isParty($user);
    }

    public function create(User $user, CoachDiscipleRelationship $relationship): bool
    {
        return $relationship->isParty($user);
    }

    public function view(User $user, RelationshipMessage $message): bool
    {
        $relationship = $message->relationLoaded('relationship')
            ? $message->relationship
            : $message->relationship()->first();

        return $relationship !== null && $relationship->isParty($user);
    }
}
