<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('company_settings', 'nova_voice_enabled')) {
            Schema::table('company_settings', function (Blueprint $table) {
                $table->boolean('nova_voice_enabled')->default(true);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('company_settings', 'nova_voice_enabled')) {
            Schema::table('company_settings', function (Blueprint $table) {
                $table->dropColumn('nova_voice_enabled');
            });
        }
    }
};
