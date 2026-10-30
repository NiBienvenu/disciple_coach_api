<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Score (in %) a disciple needs on a level quiz before it goes to the coach.
 * Below it, the disciple retakes the quiz.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            $table->unsignedTinyInteger('pass_percent')->default(80)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            $table->dropColumn('pass_percent');
        });
    }
};
