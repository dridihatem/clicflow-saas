<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('company_settings') && ! Schema::hasColumn('company_settings', 'portal_visibility')) {
            Schema::table('company_settings', function (Blueprint $table) {
                $table->json('portal_visibility')->nullable();
            });
        }

        if (Schema::hasTable('clients') && ! Schema::hasColumn('clients', 'portal_visibility')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->json('portal_visibility')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('company_settings', 'portal_visibility')) {
            Schema::table('company_settings', fn (Blueprint $table) => $table->dropColumn('portal_visibility'));
        }
        if (Schema::hasColumn('clients', 'portal_visibility')) {
            Schema::table('clients', fn (Blueprint $table) => $table->dropColumn('portal_visibility'));
        }
    }
};
