<?php

namespace App\Http\Resources\Progress;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\LevelProgress */
class LevelProgressResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'level_id' => $this->level_id,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'level' => $this->whenLoaded('level', function () {
                return $this->level === null ? null : [
                    'id' => $this->level->id,
                    'slug' => $this->level->slug,
                    'order' => $this->level->order,
                    'previous_level_id' => $this->level->previous_level_id,
                ];
            }),
        ];
    }
}
