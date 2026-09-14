<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LevelTranslation extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'level_id',
        'language',
        'title',
        'description',
    ];

    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }
}
