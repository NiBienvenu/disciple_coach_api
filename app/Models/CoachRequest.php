<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A disciple asking an approved coach to coach them.
 * status: pending | accepted | declined | cancelled
 */
class CoachRequest extends Model
{
    public const PENDING = 'pending';

    public const ACCEPTED = 'accepted';

    public const DECLINED = 'declined';

    public const CANCELLED = 'cancelled';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'disciple_id',
        'coach_id',
        'status',
        'message',
        'responded_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'responded_at' => 'datetime',
        ];
    }

    public function disciple(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disciple_id');
    }

    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_id');
    }
}
