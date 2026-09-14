<?php

namespace App\Http\Requests\Progress;

use App\Enums\LessonProgressStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompleteLessonRequest extends FormRequest
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
            'status' => [
                'sometimes',
                'string',
                Rule::in([
                    LessonProgressStatus::Completed->value,
                    LessonProgressStatus::InProgress->value,
                ]),
            ],
        ];
    }

    public function status(): LessonProgressStatus
    {
        $value = $this->validated('status') ?? LessonProgressStatus::Completed->value;

        return LessonProgressStatus::from($value);
    }
}
