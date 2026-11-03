<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DecisionResponse extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'lesson_page_id',
        'user_id',
        'device_id',
        'choice_index',
        'language',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'choice_index' => 'integer',
        ];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(LessonPage::class, 'lesson_page_id');
    }
}
