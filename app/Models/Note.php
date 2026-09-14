<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Note extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'author_id',
        'disciple_id',
        'relationship_id',
        'session_id',
        'lesson_id',
        'body',
        'is_shared',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_shared' => 'boolean',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function disciple(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disciple_id');
    }

    public function relationship(): BelongsTo
    {
        return $this->belongsTo(CoachDiscipleRelationship::class, 'relationship_id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(CoachingSession::class, 'session_id');
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}
