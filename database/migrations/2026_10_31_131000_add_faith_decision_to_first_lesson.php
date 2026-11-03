<?php

use App\Models\Lesson;
use App\Models\LessonPage;
use App\Services\Cache\CacheKey;
use Database\Seeders\LessonContentSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\File;

/**
 * Adds the "Did you believe in Jesus through this lesson?" Yes / No page at
 * the end of the first lesson (P1L01) on servers that already loaded the
 * paged content. Text comes from content/track_connect/P1L01.json.
 */
return new class extends Migration
{
    public function up(): void
    {
        $lesson = Lesson::query()->where('code', 'P1L01')->first();
        if ($lesson === null || $lesson->pages()->where('kind', LessonPage::KIND_DECISION)->exists()) {
            return;
        }

        $data = json_decode(File::get(database_path('seeders/content/track_connect/P1L01.json')), true, 512, JSON_THROW_ON_ERROR);
        $seeder = new LessonContentSeeder;
        $order = (int) $lesson->pages()->max('order');
        foreach ($data['decision'] ?? [] as $decision) {
            $seeder->addDecisionPage($lesson, ++$order, $decision);
        }
        CacheKey::bumpRevision();
    }

    public function down(): void
    {
        // Content is data, not schema: nothing to roll back.
    }
};
