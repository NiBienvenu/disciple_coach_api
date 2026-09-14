<?php

namespace App\Services\Coaching;

use App\Enums\SessionStatus;
use App\Models\CoachDiscipleRelationship;
use App\Models\CoachingSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class SessionService
{
    /**
     * @return Collection<int, CoachingSession>
     */
    public function list(CoachDiscipleRelationship $relationship): Collection
    {
        return CoachingSession::query()
            ->where('relationship_id', $relationship->id)
            ->orderByDesc('scheduled_at')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(CoachDiscipleRelationship $relationship, User $actor, array $data): CoachingSession
    {
        if (! $relationship->isActive()) {
            throw ValidationException::withMessages([
                'relationship' => ['Cannot schedule sessions on an ended relationship.'],
            ]);
        }

        return CoachingSession::query()->create([
            'relationship_id' => $relationship->id,
            'coach_id' => $relationship->coach_id,
            'disciple_id' => $relationship->disciple_id,
            'created_by' => $actor->id,
            'title' => $data['title'] ?? null,
            'scheduled_at' => $data['scheduled_at'],
            'duration_min' => $data['duration_min'] ?? 60,
            'meeting_link' => $data['meeting_link'] ?? null,
            'location' => $data['location'] ?? null,
            'agenda' => $data['agenda'] ?? null,
            'notes' => $data['notes'] ?? null,
            'action_items' => $data['action_items'] ?? null,
            'status' => SessionStatus::Scheduled,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(CoachingSession $session, array $data): CoachingSession
    {
        $session->fill(collect($data)->only([
            'title',
            'scheduled_at',
            'duration_min',
            'meeting_link',
            'location',
            'agenda',
            'notes',
            'action_items',
            'status',
        ])->all());

        $session->save();

        return $session->fresh();
    }
}
