<?php

namespace App\Models;

use App\Enums\ContentStatus;
use App\Enums\LessonType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lesson extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'level_id',
        'code',
        'slug',
        'type',
        'order',
        'status',
        'resource_url',
        'video_url',
        'image_url',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order' => 'integer',
            'type' => LessonType::class,
            'status' => ContentStatus::class,
        ];
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }

    public function translations(): HasMany
    {
        return $this->hasMany(LessonTranslation::class);
    }

    public function pages(): HasMany
    {
        return $this->hasMany(LessonPage::class)->orderBy('order')->orderBy('id');
    }

    /**
     * Images are stored as a public path (`/uploads/lessons/…`); the apps
     * get a full URL on the current host.
     */
    public function imagePublicUrl(): ?string
    {
        return $this->image_url ? url($this->image_url) : null;
    }

    public function translationFor(string $language): ?LessonTranslation
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
