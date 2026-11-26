<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('trello_board_id', 64)->nullable()->after('description');
            $table->string('notion_page_id', 64)->nullable()->after('trello_board_id');
            $table->index(['company_id', 'trello_board_id']);
            $table->index(['company_id', 'notion_page_id']);
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'trello_board_id']);
            $table->dropIndex(['company_id', 'notion_page_id']);
            $table->dropColumn(['trello_board_id', 'notion_page_id']);
        });
    }
};
