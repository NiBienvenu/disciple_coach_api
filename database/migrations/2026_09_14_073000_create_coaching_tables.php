<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invites', function (Blueprint $table) {
            $table->id();
            $table->string('code', 16)->unique();
            $table->foreignId('coach_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('used')->default(false)->index();
            $table->timestamp('expires_at')->index();
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });

        Schema::create('coach_disciple_relationships', function (Blueprint $table) {
            $table->id();
            $table->string('invite_code', 16)->nullable()->index();
            $table->foreignId('coach_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('disciple_id')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('active')->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamp('last_message_at')->nullable()->index();
            $table->json('last_read_at')->nullable();
            $table->timestamps();

            $table->index(['coach_id', 'status']);
            $table->index(['disciple_id', 'status']);
        });

        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->foreign('relationship_id')
                ->references('id')
                ->on('coach_disciple_relationships')
                ->nullOnDelete();
        });

        Schema::create('coaching_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('relationship_id')->constrained('coach_disciple_relationships')->cascadeOnDelete();
            $table->foreignId('coach_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('disciple_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->timestamp('scheduled_at')->index();
            $table->unsignedInteger('duration_min')->default(60);
            $table->string('meeting_link')->nullable();
            $table->string('location')->nullable();
            $table->text('agenda')->nullable();
            $table->text('notes')->nullable();
            $table->json('action_items')->nullable();
            $table->string('status')->default('scheduled')->index();
            $table->timestamps();

            $table->index(['coach_id', 'scheduled_at']);
            $table->index(['disciple_id', 'scheduled_at']);
        });

        Schema::create('goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('relationship_id')->constrained('coach_disciple_relationships')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('disciple_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('coach_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('not_started')->index();
            $table->date('target_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['disciple_id', 'status']);
            $table->index(['coach_id', 'status']);
        });

        Schema::create('milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goal_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->boolean('done')->default(false);
            $table->timestamp('done_at')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
        });

        Schema::create('relationship_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('relationship_id')->constrained('coach_disciple_relationships')->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->timestamp('read_at')->nullable()->index();
            $table->timestamps();

            $table->index(['relationship_id', 'created_at']);
            $table->index(['sender_id', 'created_at']);
        });

        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('disciple_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('relationship_id')->nullable()->constrained('coach_disciple_relationships')->nullOnDelete();
            $table->foreignId('session_id')->nullable()->constrained('coaching_sessions')->nullOnDelete();
            $table->foreignId('lesson_id')->nullable()->constrained('lessons')->nullOnDelete();
            $table->text('body');
            $table->boolean('is_shared')->default(false)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notes');
        Schema::dropIfExists('relationship_messages');
        Schema::dropIfExists('milestones');
        Schema::dropIfExists('goals');
        Schema::dropIfExists('coaching_sessions');

        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->dropForeign(['relationship_id']);
        });

        Schema::dropIfExists('coach_disciple_relationships');
        Schema::dropIfExists('invites');
    }
};
