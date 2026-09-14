<?php

namespace App\Policies;

use App\Models\CoachDiscipleRelationship;
use App\Models\Goal;
use App\Models\Milestone;
use App\Models\User;

class GoalPolicy
{
    public function viewAny(User $user, CoachDiscipleRelationship $relationship): bool
    {
        return $relationship->isParty($user);
    }

    public function create(User $user, CoachDiscipleRelationship $relationship): bool
    {
        return $relationship->isParty($user);
    }

    public function update(User $user, Goal $goal): bool
    {
        return (int) $user->id === (int) $goal->coach_id
            || (int) $user->id === (int) $goal->disciple_id;
    }

    public function delete(User $user, Goal $goal): bool
    {
        return $this->update($user, $goal);
    }

    public function updateMilestone(User $user, Milestone $milestone): bool
    {
        $goal = $milestone->relationLoaded('goal')
            ? $milestone->goal
            : $milestone->goal()->first();

        if ($goal === null) {
            return false;
        }

        return $this->update($user, $goal);
    }
}
