<?php

namespace App\Http\Requests\Coaching;

use App\Enums\GoalStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGoalRequest extends FormRequest
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
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', Rule::enum(GoalStatus::class)],
            'target_date' => ['sometimes', 'nullable', 'date'],
            'milestones' => ['sometimes', 'array'],
            'milestones.*.id' => ['sometimes', 'integer'],
            'milestones.*.title' => ['required_with:milestones', 'string', 'max:255'],
            'milestones.*.description' => ['nullable', 'string'],
            'milestones.*.order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
