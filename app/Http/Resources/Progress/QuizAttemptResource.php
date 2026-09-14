<?php

namespace App\Http\Resources\Progress;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\QuizAttempt */
class QuizAttemptResource extends JsonResource
{
    public function __construct(
        $resource,
        private readonly bool $forCoachReview = false,
        private readonly string $language = 'fr',
    ) {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $payload = [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'quiz_id' => $this->quiz_id,
            'level_id' => $this->level_id,
            'coach_id' => $this->coach_id,
            'relationship_id' => $this->relationship_id,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'score' => $this->score !== null ? (float) $this->score : null,
            'mentor_status' => $this->mentor_status instanceof \BackedEnum
                ? $this->mentor_status->value
                : $this->mentor_status,
            'mentor_feedback' => $this->mentor_feedback,
            'advancement_approved' => (bool) $this->advancement_approved,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'decided_at' => $this->decided_at?->toIso8601String(),
            'advancement_approved_at' => $this->advancement_approved_at?->toIso8601String(),
            'level' => $this->whenLoaded('level', function () {
                return $this->level === null ? null : [
                    'id' => $this->level->id,
                    'slug' => $this->level->slug,
                    'order' => $this->level->order,
                ];
            }),
        ];

        if ($this->relationLoaded('answers')) {
            $payload['answers'] = $this->answers->map(function ($answer) {
                $row = [
                    'quiz_question_id' => $answer->quiz_question_id,
                    'selected_index' => $answer->selected_index,
                    'is_correct' => (bool) $answer->is_correct,
                ];

                if ($this->forCoachReview) {
                    $row['correct_index'] = $answer->correct_index;
                    $question = $answer->relationLoaded('question') ? $answer->question : null;
                    if ($question !== null) {
                        $translation = $question->translationFor($this->language);
                        $row['question'] = [
                            'id' => $question->id,
                            'order' => $question->order,
                            'prompt' => $translation?->prompt,
                            'choices' => $translation?->choices ?? [],
                            'correct_index' => $question->correct_index,
                            'selected_index' => $answer->selected_index,
                            'is_correct' => (bool) $answer->is_correct,
                        ];
                    }
                }

                return $row;
            })->values()->all();
        }

        return $payload;
    }
}
