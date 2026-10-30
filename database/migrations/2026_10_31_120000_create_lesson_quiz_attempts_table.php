<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Answers to the questions at the end of a lesson. The coach approves them
 * (the next lesson opens) or resets them (the disciple answers again).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_quiz_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->foreignId('coach_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('relationship_id')->nullable();
            $table->string('status', 16)->default('pending')->index();
            $table->decimal('score', 5, 4)->nullable();
            $table->json('answers')->nullable();
            $table->text('feedback')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'lesson_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_quiz_attempts');
    }
};
