<?php

namespace App\Enums;

enum LevelProgressStatus: string
{
    case Locked = 'locked';
    case Available = 'available';
    case InProgress = 'in_progress';
    case QuizPending = 'quiz_pending';
    case CoachReview = 'coach_review';
    case Approved = 'approved';
    case NeedsReview = 'needs_review';
}
