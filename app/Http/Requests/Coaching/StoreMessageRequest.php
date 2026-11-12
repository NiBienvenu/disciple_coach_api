<?php

namespace App\Http\Requests\Coaching;

use Illuminate\Foundation\Http\FormRequest;

class StoreMessageRequest extends FormRequest
{
    /** 25 MB, in kilobytes (Laravel's unit for "max" on files). */
    public const MAX_FILE_KB = 25600;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Text, a file, or both (a photo with a caption). A voice note is a file
     * sent with kind=audio, because phones label m4a files in many ways.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'body' => ['nullable', 'string', 'max:5000', 'required_without:file'],
            'file' => ['nullable', 'file', 'max:'.self::MAX_FILE_KB, 'required_without:body'],
            'kind' => ['nullable', 'string', 'in:image,audio,file'],
            'duration_ms' => ['nullable', 'integer', 'min:0', 'max:3600000'],
            'reply_to_id' => ['nullable', 'integer'],
        ];
    }
}
