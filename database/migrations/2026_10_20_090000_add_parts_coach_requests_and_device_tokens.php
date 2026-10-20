<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Curriculum "parts": 1 Connecte ta vie à Jésus, 2 Bâtis ton fondement, 3 Faire des disciples.
        Schema::table('levels', function (Blueprint $table) {
            $table->unsignedTinyInteger('part')->default(3)->after('order')->index();
        });

        Schema::table('level_translations', function (Blueprint $table) {
            $table->string('part_title')->nullable()->after('description');
        });

        // A coach account stays "pending" until an admin approves it.
        Schema::table('users', function (Blueprint $table) {
            $table->string('coach_status', 16)->nullable()->after('is_active')->index();
        });

        // Existing coaches keep working: treat them as already approved.
        $coachRoleId = DB::table('roles')->where('name', 'coach')->value('id');
        if ($coachRoleId !== null) {
            $coachIds = DB::table('model_has_roles')
                ->where('role_id', $coachRoleId)
                ->where('model_type', 'App\\Models\\User')
                ->pluck('model_id');
            DB::table('users')->whereIn('id', $coachIds)->update(['coach_status' => 'approved']);
        }

        Schema::create('coach_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('disciple_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('coach_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 16)->default('pending')->index();
            $table->text('message')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->index(['coach_id', 'status']);
            $table->index(['disciple_id', 'status']);
        });

        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token', 512)->unique();
            $table->string('platform', 16)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_tokens');
        Schema::dropIfExists('coach_requests');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('coach_status');
        });
        Schema::table('level_translations', function (Blueprint $table) {
            $table->dropColumn('part_title');
        });
        Schema::table('levels', function (Blueprint $table) {
            $table->dropColumn('part');
        });
    }
};
