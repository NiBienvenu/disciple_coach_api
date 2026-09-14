<?php

namespace App\Policies;

use App\Models\CoachDiscipleRelationship;
use App\Models\QuizAttempt;
use App\Models\User;

class QuizAttemptPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('quiz.submit') || $user->can('quiz.review');
    }

    public function view(User $user, QuizAttempt $attempt): bool
    {
        if ($user->id === $attempt->user_id) {
            return true;
        }

        return $this->isAssignedCoach($user, $attempt->user_id);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function decide(User $user, QuizAttempt $attempt): bool
    {
        if ($user->id === $attempt->user_id) {
            return false;
        }

        return $this->isAssignedCoach($user, $attempt->user_id);
    }

    public function viewDisciple(User $coach, User $disciple): bool
    {
        return $this->isAssignedCoach($coach, $disciple->id);
    }

    private function isAssignedCoach(User $coach, int $discipleId): bool
    {
        return CoachDiscipleRelationship::query()
            ->active()
            ->where('coach_id', $coach->id)
            ->where('disciple_id', $discipleId)
            ->exists();
    }
}
