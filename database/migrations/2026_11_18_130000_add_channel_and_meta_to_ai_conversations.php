<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('ai_conversations', 'channel')) {
            Schema::table('ai_conversations', function (Blueprint $table) {
                $table->string('channel', 24)->nullable()->after('user_id');
                $table->index(['company_id', 'user_id', 'channel', 'updated_at'], 'ai_conv_channel_idx');
            });
        }
        if (! Schema::hasColumn('ai_conversation_messages', 'meta')) {
            Schema::table('ai_conversation_messages', function (Blueprint $table) {
                $table->json('meta')->nullable()->after('content');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('ai_conversation_messages', 'meta')) {
            Schema::table('ai_conversation_messages', fn (Blueprint $table) => $table->dropColumn('meta'));
        }
        if (Schema::hasColumn('ai_conversations', 'channel')) {
            Schema::table('ai_conversations', function (Blueprint $table) {
                $table->dropIndex('ai_conv_channel_idx');
                $table->dropColumn('channel');
            });
        }
    }
};
