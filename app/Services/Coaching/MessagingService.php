<?php

namespace App\Services\Coaching;

use App\Models\CoachDiscipleRelationship;
use App\Models\RelationshipMessage;
use App\Models\User;
use App\Notifications\NewRelationshipMessageNotification;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MessagingService
{
    private const PER_PAGE = 30;

    /**
     * @return array{messages: CursorPaginator, unread_count: int}
     */
    public function list(CoachDiscipleRelationship $relationship, User $viewer, ?string $cursor = null): array
    {
        $paginator = RelationshipMessage::query()
            ->where('relationship_id', $relationship->id)
            ->with('sender:id,name,email,profile_photo')
            ->orderByDesc('id')
            ->cursorPaginate(self::PER_PAGE, ['*'], 'cursor', $cursor);

        return [
            'messages' => $paginator,
            'unread_count' => $this->unreadCount($relationship, $viewer),
        ];
    }

    public function send(CoachDiscipleRelationship $relationship, User $sender, string $body): RelationshipMessage
    {
        if (! $relationship->isActive()) {
            throw ValidationException::withMessages([
                'relationship' => ['Cannot send messages on an ended relationship.'],
            ]);
        }

        $message = DB::transaction(function () use ($relationship, $sender, $body) {
            $message = RelationshipMessage::query()->create([
                'relationship_id' => $relationship->id,
                'sender_id' => $sender->id,
                'body' => $body,
            ]);

            $relationship->update([
                'last_message_at' => $message->created_at,
            ]);

            return $message;
        });

        $message->load('sender:id,name,email,profile_photo');

        $recipientId = $relationship->otherPartyId($sender);
        if ($recipientId !== null) {
            $recipient = User::query()->find($recipientId);
            if ($recipient !== null) {
                $recipient->notify(new NewRelationshipMessageNotification($message));
            }
        }

        return $message;
    }

    public function markRead(CoachDiscipleRelationship $relationship, User $reader): CoachDiscipleRelationship
    {
        $lastRead = $relationship->last_read_at ?? [];
        $lastRead[(string) $reader->id] = now()->toIso8601String();

        $relationship->update(['last_read_at' => $lastRead]);

        RelationshipMessage::query()
            ->where('relationship_id', $relationship->id)
            ->where('sender_id', '!=', $reader->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return $relationship->fresh();
    }

    public function unreadCount(CoachDiscipleRelationship $relationship, User $viewer): int
    {
        $since = $relationship->lastReadAtFor($viewer->id);

        $query = RelationshipMessage::query()
            ->where('relationship_id', $relationship->id)
            ->where('sender_id', '!=', $viewer->id);

        if ($since !== null) {
            $query->where('created_at', '>', $since);
        }

        return $query->count();
    }
}
