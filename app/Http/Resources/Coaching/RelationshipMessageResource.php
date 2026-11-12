<?php

namespace App\Http\Resources\Coaching;

use App\Models\RelationshipMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\RelationshipMessage */
class RelationshipMessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'relationship_id' => $this->relationship_id,
            'sender_id' => $this->sender_id,
            'body' => $this->body,
            'type' => $this->type ?? RelationshipMessage::TYPE_TEXT,
            'attachment' => self::attachment($this->resource),
            'reply_to' => $this->whenLoaded('replyTo', fn () => $this->replyTo === null ? null : [
                'id' => $this->replyTo->id,
                'sender_id' => $this->replyTo->sender_id,
                'body' => $this->replyTo->body,
                'type' => $this->replyTo->type ?? RelationshipMessage::TYPE_TEXT,
                'attachment_name' => $this->replyTo->attachment_name,
            ]),
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'sender' => $this->whenLoaded('sender', fn () => $this->sender === null ? null : [
                'id' => $this->sender->id,
                'name' => $this->sender->name,
                'email' => $this->sender->email,
                'profile_photo' => $this->sender->profile_photo,
            ]),
        ];
    }

    /**
     * File info. The file sits in public/uploads/chat under a random name,
     * so the link is the plain public address (like lesson images).
     *
     * @return array<string, mixed>|null
     */
    public static function attachment(RelationshipMessage $message): ?array
    {
        if (! $message->hasAttachment()) {
            return null;
        }

        return [
            'url' => url($message->attachment_path),
            'name' => $message->attachment_name,
            'mime' => $message->attachment_mime,
            'size' => $message->attachment_size,
            'duration_ms' => $message->duration_ms,
        ];
    }
}
