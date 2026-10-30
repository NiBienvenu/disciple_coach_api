<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LessonPageTranslation extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'lesson_page_id',
        'language',
        'title',
        'body',
        'choices',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'choices' => 'array',
        ];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(LessonPage::class, 'lesson_page_id');
    }
}
