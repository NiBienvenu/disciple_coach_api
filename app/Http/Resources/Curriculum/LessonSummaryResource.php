<?php

namespace App\Http\Resources\Curriculum;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Lesson */
class LessonSummaryResource extends JsonResource
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
            'resource_url' => $this->resource_url,
            'title' => $translation?->title,
        ];
    }
}
