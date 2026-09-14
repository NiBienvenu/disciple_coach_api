<?php

namespace App\Http\Resources\Curriculum;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Level */
class LevelResource extends JsonResource
{
    public function __construct($resource, private readonly string $language = 'fr', private readonly bool $withLessons = false)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $translation = $this->translationFor($this->language);

        $data = [
            'id' => $this->id,
            'slug' => $this->slug,
            'order' => $this->order,
            'previous_level_id' => $this->previous_level_id,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'title' => $translation?->title,
            'description' => $translation?->description,
        ];

        if ($this->withLessons || $this->relationLoaded('lessons')) {
            $data['lessons'] = $this->whenLoaded('lessons', function () {
                return $this->lessons->map(
                    fn ($lesson) => (new LessonSummaryResource($lesson, $this->language))->resolve()
                )->values()->all();
            });
        }

        return $data;
    }
}
