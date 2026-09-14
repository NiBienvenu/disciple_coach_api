<?php

namespace App\Http\Requests\Progress;

use Illuminate\Foundation\Http\FormRequest;

class SubmitQuizRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'answers' => ['required', 'array', 'min:1'],
            'answers.*.question_id' => ['required', 'integer', 'distinct'],
            'answers.*.selected_index' => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * @return list<array{question_id: int, selected_index: int}>
     */
    public function answers(): array
    {
        /** @var list<array{question_id: int|string, selected_index: int|string}> $answers */
        $answers = $this->validated('answers');

        return array_map(
            fn (array $row) => [
                'question_id' => (int) $row['question_id'],
                'selected_index' => (int) $row['selected_index'],
            ],
            $answers,
        );
    }
}
