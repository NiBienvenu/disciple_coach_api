<?php

namespace App\Http\Resources\Coaching;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\CoachingSession */
class CoachingSessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'relationship_id' => $this->relationship_id,
            'coach_id' => $this->coach_id,
            'disciple_id' => $this->disciple_id,
            'created_by' => $this->created_by,
            'title' => $this->title,
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'duration_min' => $this->duration_min,
            'meeting_link' => $this->meeting_link,
            'location' => $this->location,
            'agenda' => $this->agenda,
            'notes' => $this->notes,
            'action_items' => $this->action_items ?? [],
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
