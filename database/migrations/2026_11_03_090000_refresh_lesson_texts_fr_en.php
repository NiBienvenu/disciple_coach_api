<?php

use Database\Seeders\LessonContentSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Loads the rewritten French and English lesson text (content/*.json) on
 * servers that already have the paged lessons. Text only, in place: pages,
 * images, Kirundi and the answers people gave are kept.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Tests migrate for every case; they build their own small content.
        if (app()->environment('testing')) {
            return;
        }

        (new LessonContentSeeder)->refreshTexts(['fr', 'en']);
    }

    public function down(): void
    {
        // Content is data, not schema: nothing to roll back.
    }
};
