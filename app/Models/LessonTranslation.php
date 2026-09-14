<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LessonTranslation extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'lesson_id',
        'language',
        'title',
        'central_idea',
        'objectives',
        'key_scriptures',
        'summary_points',
        'reflection_questions',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'objectives' => 'array',
            'key_scriptures' => 'array',
            'summary_points' => 'array',
            'reflection_questions' => 'array',
        ];
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}
