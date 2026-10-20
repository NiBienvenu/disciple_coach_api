<?php

namespace App\Http\Resources\Coaching;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\CoachRequest */
class CoachRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $person = fn ($u) => $u === null ? null : [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'profile_photo' => $u->profile_photo,
        ];

        return [
            'id' => $this->id,
            'coach_id' => $this->coach_id,
            'disciple_id' => $this->disciple_id,
            'status' => $this->status,
            'message' => $this->message,
            'coach' => $this->whenLoaded('coach', fn () => $person($this->coach)),
            'disciple' => $this->whenLoaded('disciple', fn () => $person($this->disciple)),
            'responded_at' => $this->responded_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
