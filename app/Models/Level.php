<?php

namespace App\Models;

use App\Enums\ContentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Level extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'slug',
        'order',
        'previous_level_id',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order' => 'integer',
            'status' => ContentStatus::class,
        ];
    }

    public function previousLevel(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_level_id');
    }

    public function nextLevels(): HasMany
    {
        return $this->hasMany(self::class, 'previous_level_id');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(LevelTranslation::class);
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class)->orderBy('order');
    }

    public function quiz(): HasOne
    {
        return $this->hasOne(Quiz::class);
    }

    public function translationFor(string $language): ?LevelTranslation
    {
        if ($this->relationLoaded('translations')) {
            return $this->translations->firstWhere('language', $language)
                ?? $this->translations->firstWhere('language', 'fr')
                ?? $this->translations->first();
        }

        return $this->translations()->where('language', $language)->first()
            ?? $this->translations()->where('language', 'fr')->first()
            ?? $this->translations()->first();
    }
}
