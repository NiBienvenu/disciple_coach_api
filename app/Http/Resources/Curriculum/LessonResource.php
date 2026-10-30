<?php

namespace App\Http\Resources\Curriculum;

use App\Models\LessonPage;
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
            'video_url' => $this->video_url,
            'image_url' => $this->imagePublicUrl(),
            'title' => $translation?->title,
            'content' => [
                'central_idea' => $translation?->central_idea,
                'body' => $translation?->body,
                'objectives' => $translation?->objectives ?? [],
                'key_scriptures' => $translation?->key_scriptures ?? [],
                'summary_points' => $translation?->summary_points ?? [],
                'reflection_questions' => $translation?->reflection_questions ?? [],
            ],
            // Gnowbe-style pages. Question pages are self-checks, so the
            // correct answer is sent (the graded quiz is the level quiz).
            'pages' => $this->relationLoaded('pages')
                ? $this->pages->map(fn (LessonPage $page) => $this->pagePayload($page))->values()->all()
                : [],
        ];
    }

    /** @return array<string, mixed> */
    private function pagePayload(LessonPage $page): array
    {
        $t = $page->translationFor($this->language);

        return [
            'id' => $page->id,
            'order' => $page->order,
            'kind' => $page->kind,
            'image_url' => $page->imagePublicUrl(),
            'video_url' => $page->video_url,
            'title' => $t?->title,
            'body' => $t?->body,
            'choices' => $page->isQuestion() ? ($t?->choices ?? []) : null,
            'correct_index' => $page->isQuestion() ? $page->correct_index : null,
        ];
    }
}
