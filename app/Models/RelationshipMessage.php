<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RelationshipMessage extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'relationship_id',
        'sender_id',
        'body',
        'type',
        'reply_to_id',
        'attachment_path',
        'attachment_name',
        'attachment_mime',
        'attachment_size',
        'duration_ms',
        'read_at',
    ];

    public const TYPE_TEXT = 'text';

    public const TYPE_IMAGE = 'image';

    public const TYPE_AUDIO = 'audio';

    public const TYPE_FILE = 'file';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
            'attachment_size' => 'integer',
            'duration_ms' => 'integer',
        ];
    }

    public function relationship(): BelongsTo
    {
        return $this->belongsTo(CoachDiscipleRelationship::class, 'relationship_id');
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to_id');
    }

    public function hasAttachment(): bool
    {
        return $this->attachment_path !== null;
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
