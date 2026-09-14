<?php

namespace App\Http\Requests\Progress;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DecideQuizAttemptRequest extends FormRequest
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
            'decision' => ['required', 'string', Rule::in(['approve', 'reject'])],
            'feedback' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function decision(): string
    {
        return (string) $this->validated('decision');
    }

    public function feedback(): ?string
    {
        $feedback = $this->validated('feedback');

        return is_string($feedback) && $feedback !== '' ? $feedback : null;
    }
}
