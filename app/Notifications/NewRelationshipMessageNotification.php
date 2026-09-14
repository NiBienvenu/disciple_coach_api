<?php

namespace App\Notifications;

use App\Models\RelationshipMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class NewRelationshipMessageNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly RelationshipMessage $message,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'relationship_message',
            'message_id' => $this->message->id,
            'relationship_id' => $this->message->relationship_id,
            'sender_id' => $this->message->sender_id,
            'body_preview' => mb_substr($this->message->body, 0, 120),
            'created_at' => $this->message->created_at?->toIso8601String(),
        ];
    }
}
