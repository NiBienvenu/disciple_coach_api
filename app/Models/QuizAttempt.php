<?php

namespace App\Models;

use App\Enums\MentorReviewStatus;
use App\Enums\QuizAttemptStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuizAttempt extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'quiz_id',
        'level_id',
        'coach_id',
        'relationship_id',
        'status',
        'score',
        'mentor_status',
        'mentor_feedback',
        'advancement_approved',
        'submitted_at',
        'decided_at',
        'advancement_approved_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => QuizAttemptStatus::class,
            'mentor_status' => MentorReviewStatus::class,
            'score' => 'decimal:4',
            'advancement_approved' => 'boolean',
            'submitted_at' => 'datetime',
            'decided_at' => 'datetime',
            'advancement_approved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }

    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_id');
    }

    public function relationship(): BelongsTo
    {
        return $this->belongsTo(CoachDiscipleRelationship::class, 'relationship_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(QuizAnswer::class);
    }
}
