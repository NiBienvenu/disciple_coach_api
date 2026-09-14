<?php

namespace App\Models;

use App\Enums\SessionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CoachingSession extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'relationship_id',
        'coach_id',
        'disciple_id',
        'created_by',
        'title',
        'scheduled_at',
        'duration_min',
        'meeting_link',
        'location',
        'agenda',
        'notes',
        'action_items',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'action_items' => 'array',
            'status' => SessionStatus::class,
            'duration_min' => 'integer',
        ];
    }

    public function relationship(): BelongsTo
    {
        return $this->belongsTo(CoachDiscipleRelationship::class, 'relationship_id');
    }

    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_id');
    }

    public function disciple(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disciple_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sessionNotes(): HasMany
    {
        return $this->hasMany(Note::class, 'session_id');
    }
}
