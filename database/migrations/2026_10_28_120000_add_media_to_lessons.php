<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional lesson media: a YouTube link and an uploaded image (shared by all
 * languages), plus a free-text body per language.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->string('video_url')->nullable()->after('resource_url');
            $table->string('image_url')->nullable()->after('video_url');
        });

        Schema::table('lesson_translations', function (Blueprint $table) {
            $table->longText('body')->nullable()->after('central_idea');
        });
    }

    public function down(): void
    {
        Schema::table('lesson_translations', function (Blueprint $table) {
            $table->dropColumn('body');
        });

        Schema::table('lessons', function (Blueprint $table) {
            $table->dropColumn(['video_url', 'image_url']);
        });
    }
};
