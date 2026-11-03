<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Answers to "decision" pages (e.g. "Did you believe in Jesus through this
 * lesson?" Yes / No). One answer per page and person: a signed-in user, or a
 * visitor known by a random id kept on their phone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('decision_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_page_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('device_id', 64)->nullable();
            $table->unsignedTinyInteger('choice_index');
            $table->string('language', 8)->nullable();
            $table->timestamps();

            $table->index(['lesson_page_id', 'choice_index']);
            $table->index(['lesson_page_id', 'user_id']);
            $table->index(['lesson_page_id', 'device_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('decision_responses');
    }
};
