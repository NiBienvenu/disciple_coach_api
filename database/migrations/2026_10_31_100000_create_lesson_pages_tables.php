<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gnowbe-style lessons: a lesson is a list of short pages. A page is either
 * content (title, text, optional image and video) or a practice question
 * (choices + correct answer, explanation in the body).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('order')->default(0);
            $table->string('kind', 16)->default('content');
            $table->string('image_url')->nullable();
            $table->string('video_url', 500)->nullable();
            $table->unsignedTinyInteger('correct_index')->nullable();
            $table->timestamps();

            $table->index(['lesson_id', 'order']);
        });

        Schema::create('lesson_page_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_page_id')->constrained()->cascadeOnDelete();
            $table->string('language', 8);
            $table->string('title')->nullable();
            $table->longText('body')->nullable();
            $table->json('choices')->nullable();
            $table->timestamps();

            $table->unique(['lesson_page_id', 'language']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_page_translations');
        Schema::dropIfExists('lesson_pages');
    }
};
