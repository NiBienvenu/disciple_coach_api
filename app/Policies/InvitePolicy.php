<?php

namespace App\Policies;

use App\Models\Invite;
use App\Models\User;

class InvitePolicy
{
    public function create(User $user): bool
    {
        return $user->can('coaching.manage');
    }

    public function redeem(User $user): bool
    {
        return $user->exists;
    }

    public function view(User $user, Invite $invite): bool
    {
        return (int) $user->id === (int) $invite->coach_id;
    }
}
