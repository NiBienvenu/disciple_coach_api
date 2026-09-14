<?php

namespace App\Http\Resources\Coaching;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Note */
class NoteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'author_id' => $this->author_id,
            'disciple_id' => $this->disciple_id,
            'relationship_id' => $this->relationship_id,
            'session_id' => $this->session_id,
            'lesson_id' => $this->lesson_id,
            'body' => $this->body,
            'is_shared' => (bool) $this->is_shared,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
