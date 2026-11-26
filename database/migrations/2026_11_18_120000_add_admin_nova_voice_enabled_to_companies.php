<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('companies', 'admin_nova_voice_enabled')) {
            Schema::table('companies', function (Blueprint $table) {
                // Platform-admin switch; the company's own preference stays in company_settings.nova_voice_enabled.
                $table->boolean('admin_nova_voice_enabled')->default(true);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('companies', 'admin_nova_voice_enabled')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->dropColumn('admin_nova_voice_enabled');
            });
        }
    }
};
