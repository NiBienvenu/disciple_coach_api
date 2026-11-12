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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MessagingService
{
    private const PER_PAGE = 30;

    /** Chat files live in public/uploads/chat/<relationship>, served as-is
     * by the web server (like lesson images), under random names. */
    public const FILE_DIR = 'uploads/chat';

    /** Only these keep their extension; anything else is saved as .bin so
     * the web server can never run it (no .php, .html, .js…). */
    private const SAFE_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'heic', 'heif',
        'm4a', 'aac', 'mp3', 'ogg', 'opus', 'wav', 'webm', 'mp4', 'mov', '3gp',
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp', 'txt', 'csv', 'rtf', 'zip',
    ];

    /** @var array<int, string> */
    private const WITH = [
        'sender:id,name,email,profile_photo',
        'replyTo:id,sender_id,body,type,attachment_name',
    ];

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
            ->with(self::WITH)
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
            ->with(self::WITH)
            ->orderByDesc('id')
            ->cursorPaginate(self::PER_PAGE, ['*'], 'cursor', $cursor);

        return [
            'messages' => $paginator,
            'unread_count' => $this->unreadCount($relationship, $viewer),
        ];
    }

    /**
     * Text, a file (photo, document, voice note) or both, optionally as a
     * reply to an earlier message of the same chat.
     */
    public function send(
        CoachDiscipleRelationship $relationship,
        User $sender,
        ?string $body,
        ?UploadedFile $file = null,
        ?string $kind = null,
        ?int $durationMs = null,
        ?int $replyToId = null,
    ): RelationshipMessage {
        if (! $relationship->isActive()) {
            throw ValidationException::withMessages([
                'relationship' => ['Cannot send messages on an ended relationship.'],
            ]);
        }

        $body = trim((string) $body);
        if ($body === '' && $file === null) {
            throw ValidationException::withMessages(['body' => ['The message is empty.']]);
        }

        if ($replyToId !== null && ! RelationshipMessage::query()
            ->where('relationship_id', $relationship->id)
            ->whereKey($replyToId)
            ->exists()) {
            throw ValidationException::withMessages(['reply_to_id' => ['This message is not in this chat.']]);
        }

        $attachment = $file !== null ? $this->storeFile($relationship, $file, $kind, $durationMs) : [];

        $message = DB::transaction(function () use ($relationship, $sender, $body, $replyToId, $attachment) {
            $message = RelationshipMessage::query()->create([
                'relationship_id' => $relationship->id,
                'sender_id' => $sender->id,
                'body' => $body,
                'type' => RelationshipMessage::TYPE_TEXT,
                'reply_to_id' => $replyToId,
                ...$attachment,
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

        $message->load(self::WITH);

        $recipientId = $relationship->otherPartyId($sender);
        if ($recipientId !== null) {
            $recipient = User::query()->find($recipientId);
            if ($recipient !== null) {
                $recipient->notify(new NewRelationshipMessageNotification($message));
                $this->push->notify($recipient, $sender->name, self::preview($message), [
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
     * Short text for the push banner and chat list, like WhatsApp:
     * "📷 Photo", "🎤 Voice message (0:12)", "📎 report.pdf".
     *
     * @return array<string, string>|string
     */
    public static function preview(RelationshipMessage $message): array|string
    {
        $text = mb_substr((string) $message->body, 0, 140);

        return match ($message->type) {
            RelationshipMessage::TYPE_IMAGE => [
                'fr' => '📷 '.($text !== '' ? $text : 'Photo'),
                'en' => '📷 '.($text !== '' ? $text : 'Photo'),
            ],
            RelationshipMessage::TYPE_AUDIO => [
                'fr' => '🎤 Message vocal'.self::duration($message->duration_ms),
                'en' => '🎤 Voice message'.self::duration($message->duration_ms),
            ],
            RelationshipMessage::TYPE_FILE => '📎 '.($text !== '' ? $text : (string) $message->attachment_name),
            default => $text,
        };
    }

    private static function duration(?int $ms): string
    {
        if ($ms === null || $ms <= 0) {
            return '';
        }
        $seconds = intdiv($ms, 1000);

        return sprintf(' (%d:%02d)', intdiv($seconds, 60), $seconds % 60);
    }

    /**
     * Saves the file on the private disk and returns the columns to store.
     *
     * @return array<string, mixed>
     */
    private function storeFile(CoachDiscipleRelationship $relationship, UploadedFile $file, ?string $kind, ?int $durationMs): array
    {
        $mime = (string) ($file->getMimeType() ?: $file->getClientMimeType() ?: 'application/octet-stream');
        $type = match (true) {
            $kind === RelationshipMessage::TYPE_AUDIO => RelationshipMessage::TYPE_AUDIO,
            $kind === RelationshipMessage::TYPE_FILE => RelationshipMessage::TYPE_FILE,
            str_starts_with($mime, 'image/') && $mime !== 'image/svg+xml' => RelationshipMessage::TYPE_IMAGE,
            str_starts_with($mime, 'audio/') => RelationshipMessage::TYPE_AUDIO,
            default => RelationshipMessage::TYPE_FILE,
        };

        $original = basename(str_replace('\\', '/', (string) $file->getClientOriginalName()));
        $extension = strtolower($file->getClientOriginalExtension() ?: ($file->guessExtension() ?? 'bin'));
        if (! in_array($extension, self::SAFE_EXTENSIONS, true)) {
            $extension = 'bin';
        }
        $name = $original !== '' ? Str::limit($original, 180, '') : $type.'.'.$extension;

        $dir = self::FILE_DIR.'/'.$relationship->id;
        if (! is_dir(public_path($dir))) {
            mkdir(public_path($dir), 0755, true);
        }
        $fileName = Str::uuid().'.'.$extension;
        $size = $file->getSize();
        $file->move(public_path($dir), $fileName);
        $path = '/'.$dir.'/'.$fileName;

        return [
            'type' => $type,
            'attachment_path' => $path,
            'attachment_name' => $name,
            'attachment_mime' => $mime,
            'attachment_size' => $size,
            'duration_ms' => $type === RelationshipMessage::TYPE_AUDIO ? $durationMs : null,
        ];
    }

    /** When the other person last opened this chat (for the blue ticks). */
    public function otherReadAt(CoachDiscipleRelationship $relationship, User $viewer): ?string
    {
        $otherId = $relationship->otherPartyId($viewer);

        return $otherId === null ? null : $relationship->lastReadAtFor($otherId)?->toIso8601String();
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
