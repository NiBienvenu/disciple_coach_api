<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LessonPage extends Model
{
    public const KIND_CONTENT = 'content';

    public const KIND_QUESTION = 'question';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'lesson_id',
        'order',
        'kind',
        'image_url',
        'video_url',
        'correct_index',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order' => 'integer',
            'correct_index' => 'integer',
        ];
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function translations(): HasMany
    {
        return $this->hasMany(LessonPageTranslation::class);
    }

    public function isQuestion(): bool
    {
        return $this->kind === self::KIND_QUESTION;
    }

    /** Same rule as lesson images: stored as a public path, served as a full URL. */
    public function imagePublicUrl(): ?string
    {
        return $this->image_url ? url($this->image_url) : null;
    }

    public function translationFor(string $language): ?LessonPageTranslation
    {
        $translations = $this->relationLoaded('translations') ? $this->translations : $this->translations()->get();

        return $translations->firstWhere('language', $language)
            ?? $translations->firstWhere('language', 'fr')
            ?? $translations->first();
    }
}
