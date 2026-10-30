<?php

namespace App\Models;

use App\Enums\ContentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quiz extends Model
{
    public const DEFAULT_PASS_PERCENT = 80;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'level_id',
        'status',
        'pass_percent',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ContentStatus::class,
            'pass_percent' => 'integer',
        ];
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }

    public function passPercent(): int
    {
        return (int) ($this->pass_percent ?? self::DEFAULT_PASS_PERCENT);
    }

    /** True when [score] (0..1) reaches this quiz's pass mark. */
    public function isPassing(float $score): bool
    {
        return round($score * 100, 2) >= $this->passPercent();
    }

    public function questions(): HasMany
    {
        return $this->hasMany(QuizQuestion::class)->orderBy('order');
    }
}
