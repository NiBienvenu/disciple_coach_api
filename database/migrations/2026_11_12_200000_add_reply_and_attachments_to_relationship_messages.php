<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * WhatsApp-style chat: reply to a message, send a photo, a file or a
     * voice note. Files live on the private disk, never in public/.
     */
    public function up(): void
    {
        Schema::table('relationship_messages', function (Blueprint $table) {
            $table->string('type', 16)->default('text')->after('body');
            $table->foreignId('reply_to_id')->nullable()->after('type')
                ->constrained('relationship_messages')->nullOnDelete();
            $table->string('attachment_path')->nullable()->after('reply_to_id');
            $table->string('attachment_name')->nullable()->after('attachment_path');
            $table->string('attachment_mime', 128)->nullable()->after('attachment_name');
            $table->unsignedBigInteger('attachment_size')->nullable()->after('attachment_mime');
            $table->unsignedInteger('duration_ms')->nullable()->after('attachment_size');
        });
    }

    public function down(): void
    {
        Schema::table('relationship_messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reply_to_id');
            $table->dropColumn(['type', 'attachment_path', 'attachment_name', 'attachment_mime', 'attachment_size', 'duration_ms']);
        });
    }
};
