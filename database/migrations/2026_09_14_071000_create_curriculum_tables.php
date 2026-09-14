<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('levels', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->unsignedInteger('order')->index();
            $table->foreignId('previous_level_id')->nullable()->constrained('levels')->nullOnDelete();
            $table->string('status')->default('published')->index();
            $table->timestamps();
        });

        Schema::create('level_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('level_id')->constrained()->cascadeOnDelete();
            $table->string('language', 10);
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['level_id', 'language']);
            $table->index('language');
        });

        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('level_id')->constrained()->cascadeOnDelete();
            $table->string('code')->unique();
            $table->string('slug')->unique();
            $table->string('type')->default('main')->index();
            $table->unsignedInteger('order')->index();
            $table->string('status')->default('published')->index();
            $table->timestamps();

            $table->index(['level_id', 'order']);
        });

        Schema::create('lesson_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->string('language', 10);
            $table->string('title');
            $table->text('central_idea')->nullable();
            $table->json('objectives')->nullable();
            $table->json('key_scriptures')->nullable();
            $table->json('summary_points')->nullable();
            $table->json('reflection_questions')->nullable();
            $table->timestamps();

            $table->unique(['lesson_id', 'language']);
            $table->index('language');
        });

        Schema::create('quizzes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('level_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('published');
            $table->timestamps();

            $table->unique('level_id');
        });

        Schema::create('quiz_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('order')->default(0);
            $table->unsignedInteger('correct_index');
            $table->timestamps();

            $table->index('quiz_id');
        });

        Schema::create('quiz_question_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_question_id')->constrained()->cascadeOnDelete();
            $table->string('language', 10);
            $table->text('prompt');
            $table->json('choices');
            $table->timestamps();

            $table->unique(['quiz_question_id', 'language'], 'quiz_q_trans_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_question_translations');
        Schema::dropIfExists('quiz_questions');
        Schema::dropIfExists('quizzes');
        Schema::dropIfExists('lesson_translations');
        Schema::dropIfExists('lessons');
        Schema::dropIfExists('level_translations');
        Schema::dropIfExists('levels');
    }
};
