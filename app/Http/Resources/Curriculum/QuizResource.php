<?php

namespace App\Http\Resources\Curriculum;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Quiz */
class QuizResource extends JsonResource
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
        return [
            'id' => $this->id,
            'level_id' => $this->level_id,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'questions' => $this->whenLoaded('questions', function () {
                return $this->questions->map(function ($question) {
                    $translation = $question->translationFor($this->language);

                    return [
                        'id' => $question->id,
                        'order' => $question->order,
                        'prompt' => $translation?->prompt,
                        'choices' => $translation?->choices ?? [],
                    ];
                })->values()->all();
            }),
        ];
    }
}
