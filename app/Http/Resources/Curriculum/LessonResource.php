<?php

namespace App\Http\Resources\Curriculum;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Lesson */
class LessonResource extends JsonResource
{
    public function __construct($resource, private readonly string $language = 'fr')
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $translation = $this->translationFor($this->language);

        return [
            'id' => $this->id,
            'level_id' => $this->level_id,
            'code' => $this->code,
            'slug' => $this->slug,
            'type' => $this->type instanceof \BackedEnum ? $this->type->value : $this->type,
            'order' => $this->order,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'resource_url' => $this->resource_url,
            'title' => $translation?->title,
            'content' => [
                'central_idea' => $translation?->central_idea,
                'objectives' => $translation?->objectives ?? [],
                'key_scriptures' => $translation?->key_scriptures ?? [],
                'summary_points' => $translation?->summary_points ?? [],
                'reflection_questions' => $translation?->reflection_questions ?? [],
            ],
        ];
    }
}
