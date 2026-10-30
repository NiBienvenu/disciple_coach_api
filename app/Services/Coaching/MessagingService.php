<?php

namespace App\Services\Coaching;

use App\Enums\RelationshipStatus;
use App\Models\CoachDiscipleRelationship;
use App\Models\RelationshipMessage;
use App\Models\User;
use App\Notifications\NewRelationshipMessageNotification;
use App\Services\Push\PushNotifier;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MessagingService
{
    private const PER_PAGE = 30;

    public function __construct(private readonly PushNotifier $push) {}

    /**
     * Only messages newer than [afterId], newest first. Used when a push
     * says "new message" so the app fetches just the delta.
     *
     * @return Collection<int, RelationshipMessage>
     */
    public function listAfter(CoachDiscipleRelationship $relationship, int $afterId): Collection
    {
        return RelationshipMessage::query()
            ->where('relationship_id', $relationship->id)
            ->where('id', '>', $afterId)
            ->with('sender:id,name,email,profile_photo')
            ->orderByDesc('id')
            ->limit(100)
            ->get();
    }

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

            // The sender has seen their own message: it never counts as new.
            $lastRead = $relationship->last_read_at ?? [];
            $lastRead[(string) $sender->id] = $message->created_at->toIso8601String();

            $relationship->update([
                'last_message_at' => $message->created_at,
                'last_read_at' => $lastRead,
            ]);

            return $message;
        });

        $message->load('sender:id,name,email,profile_photo');

        $recipientId = $relationship->otherPartyId($sender);
        if ($recipientId !== null) {
            $recipient = User::query()->find($recipientId);
            if ($recipient !== null) {
                $recipient->notify(new NewRelationshipMessageNotification($message));
                $this->push->notify($recipient, $sender->name, mb_substr($message->body, 0, 140), [
                    'type' => 'message',
                    'relationship_id' => $relationship->id,
                    'message_id' => $message->id,
                    'tag' => 'chat_'.$relationship->id,
                ]);
            }
        }

        return $message;
    }

    /**
     * Tiny payload the app polls (and re-reads on each push) to know if any
     * chat changed: unread count and newest message id per relationship.
     *
     * @return array{unread_total: int, relationships: list<array{id: int, unread_count: int, last_message_id: int|null}>}
     */
    public function summary(User $user): array
    {
        $rows = CoachDiscipleRelationship::query()
            ->forUser($user)
            ->where('status', '!=', RelationshipStatus::Ended)
            ->withCount(['messages as unread_count' => fn ($q) => $q
                ->where('sender_id', '!=', $user->id)
                ->whereNull('read_at')])
            ->withMax('messages as last_message_id', 'id')
            ->get(['id']);

        $items = $rows->map(fn (CoachDiscipleRelationship $r) => [
            'id' => $r->id,
            'unread_count' => (int) $r->unread_count,
            'last_message_id' => $r->last_message_id !== null ? (int) $r->last_message_id : null,
        ])->values()->all();

        return [
            'unread_total' => array_sum(array_column($items, 'unread_count')),
            'relationships' => $items,
        ];
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
