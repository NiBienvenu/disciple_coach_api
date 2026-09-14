<?php

namespace App\Http\Resources\Progress;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\LessonProgress */
class LessonProgressResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'lesson_id' => $this->lesson_id,
            'level_id' => $this->whenLoaded('lesson', fn () => $this->lesson?->level_id),
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'coach_id' => $this->coach_id,
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'last_viewed_at' => $this->last_viewed_at?->toIso8601String(),
            'lesson' => $this->whenLoaded('lesson', function () {
                return $this->lesson === null ? null : [
                    'id' => $this->lesson->id,
                    'level_id' => $this->lesson->level_id,
                    'code' => $this->lesson->code,
                    'slug' => $this->lesson->slug,
                    'order' => $this->lesson->order,
                ];
            }),
        ];
    }
}
