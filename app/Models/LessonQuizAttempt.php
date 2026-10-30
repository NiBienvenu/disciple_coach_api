<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LessonQuizAttempt extends Model
{
    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    /** The coach sent it back: the disciple answers again. */
    public const RESET = 'reset';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'lesson_id',
        'coach_id',
        'relationship_id',
        'status',
        'score',
        'answers',
        'feedback',
        'submitted_at',
        'decided_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'score' => 'float',
            'answers' => 'array',
            'submitted_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function isApproved(): bool
    {
        return $this->status === self::APPROVED;
    }
}
