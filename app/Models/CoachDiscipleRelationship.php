<?php

namespace App\Models;

use App\Enums\RelationshipStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CoachDiscipleRelationship extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'invite_code',
        'coach_id',
        'disciple_id',
        'status',
        'started_at',
        'ended_at',
        'last_message_at',
        'last_read_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RelationshipStatus::class,
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'last_message_at' => 'datetime',
            'last_read_at' => 'array',
        ];
    }

    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_id');
    }

    public function disciple(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disciple_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(CoachingSession::class, 'relationship_id');
    }

    public function goals(): HasMany
    {
        return $this->hasMany(Goal::class, 'relationship_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(RelationshipMessage::class, 'relationship_id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(Note::class, 'relationship_id');
    }

    public function quizAttempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class, 'relationship_id');
    }

    public function isActive(): bool
    {
        return $this->status === RelationshipStatus::Active;
    }

    public function isParty(User $user): bool
    {
        return (int) $user->id === (int) $this->coach_id
            || (int) $user->id === (int) $this->disciple_id;
    }

    public function otherPartyId(User $user): ?int
    {
        if ((int) $user->id === (int) $this->coach_id) {
            return (int) $this->disciple_id;
        }

        if ((int) $user->id === (int) $this->disciple_id) {
            return (int) $this->coach_id;
        }

        return null;
    }

    /**
     * @param  Builder<CoachDiscipleRelationship>  $query
     * @return Builder<CoachDiscipleRelationship>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', RelationshipStatus::Active);
    }

    /**
     * @param  Builder<CoachDiscipleRelationship>  $query
     * @return Builder<CoachDiscipleRelationship>
     */
    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $q) use ($user) {
            $q->where('coach_id', $user->id)
                ->orWhere('disciple_id', $user->id);
        });
    }

    public function lastReadAtFor(int|string $userId): ?\Carbon\CarbonInterface
    {
        $raw = $this->last_read_at[(string) $userId] ?? $this->last_read_at[$userId] ?? null;

        if ($raw === null) {
            return null;
        }

        return \Illuminate\Support\Carbon::parse($raw);
    }
}
