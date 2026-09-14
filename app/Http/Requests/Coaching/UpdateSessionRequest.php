<?php

namespace App\Http\Requests\Coaching;

use App\Enums\SessionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSessionRequest extends FormRequest
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
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'scheduled_at' => ['sometimes', 'date'],
            'duration_min' => ['sometimes', 'integer', 'min:5', 'max:480'],
            'meeting_link' => ['sometimes', 'nullable', 'string', 'max:500'],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'agenda' => ['sometimes', 'nullable', 'string'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'action_items' => ['sometimes', 'nullable', 'array'],
            'action_items.*' => ['string', 'max:500'],
            'status' => ['sometimes', Rule::enum(SessionStatus::class)],
        ];
    }
}
