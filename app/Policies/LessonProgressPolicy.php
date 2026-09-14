<?php

namespace App\Policies;

use App\Models\LessonProgress;
use App\Models\User;

class LessonProgressPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('progress.own') || $user->can('progress.coach');
    }

    public function view(User $user, LessonProgress $progress): bool
    {
        if ($user->id === $progress->user_id) {
            return true;
        }

        return $user->can('progress.coach');
    }

    public function update(User $user, ?LessonProgress $progress = null): bool
    {
        if ($progress === null) {
            return $user->can('progress.own');
        }

        return $user->id === $progress->user_id && $user->can('progress.own');
    }
}
