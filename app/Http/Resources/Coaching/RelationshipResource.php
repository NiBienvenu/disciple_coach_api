<?php

namespace App\Http\Resources\Coaching;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\CoachDiscipleRelationship */
class RelationshipResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invite_code' => $this->invite_code,
            'coach_id' => $this->coach_id,
            'disciple_id' => $this->disciple_id,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'started_at' => $this->started_at?->toIso8601String(),
            'ended_at' => $this->ended_at?->toIso8601String(),
            'last_message_at' => $this->last_message_at?->toIso8601String(),
            'last_read_at' => $this->last_read_at ?? (object) [],
            'coach' => $this->whenLoaded('coach', fn () => $this->coach === null ? null : [
                'id' => $this->coach->id,
                'name' => $this->coach->name,
                'email' => $this->coach->email,
                'profile_photo' => $this->coach->profile_photo,
            ]),
            'disciple' => $this->whenLoaded('disciple', fn () => $this->disciple === null ? null : [
                'id' => $this->disciple->id,
                'name' => $this->disciple->name,
                'email' => $this->disciple->email,
                'profile_photo' => $this->disciple->profile_photo,
            ]),
            'sessions' => CoachingSessionResource::collection($this->whenLoaded('sessions')),
            'goals' => GoalResource::collection($this->whenLoaded('goals')),
            'notes' => NoteResource::collection($this->whenLoaded('notes')),
        ];
    }
}
