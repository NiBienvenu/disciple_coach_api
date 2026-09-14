<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuizQuestion extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'quiz_id',
        'order',
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

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function translations(): HasMany
    {
        return $this->hasMany(QuizQuestionTranslation::class);
    }

    public function translationFor(string $language): ?QuizQuestionTranslation
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
