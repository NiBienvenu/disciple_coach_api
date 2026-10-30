<?php

use Database\Seeders\LessonContentSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Loads the rebuilt curriculum (short pages, illustrations, end-of-lesson
 * questions) with `php artisan migrate`. Runs once; later admin edits are
 * not overwritten.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Tests migrate for every case; they build their own small content.
        if (app()->environment('testing')) {
            return;
        }

        (new LessonContentSeeder)->run();
    }

    public function down(): void
    {
        // Content is data, not schema: nothing to roll back.
    }
};
