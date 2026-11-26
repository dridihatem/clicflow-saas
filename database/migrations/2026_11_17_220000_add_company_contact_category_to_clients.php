<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (! Schema::hasColumn('clients', 'company_name')) {
                $table->string('company_name', 191)->nullable()->after('name');
            }
            if (! Schema::hasColumn('clients', 'contact_name')) {
                $table->string('contact_name', 191)->nullable()->after('company_name');
            }
            if (! Schema::hasColumn('clients', 'category')) {
                $table->string('category', 100)->nullable()->after('contact_name');
                $table->index(['company_id', 'category']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (Schema::hasColumn('clients', 'category')) {
                $table->dropIndex(['company_id', 'category']);
                $table->dropColumn('category');
            }
            foreach (['contact_name', 'company_name'] as $col) {
                if (Schema::hasColumn('clients', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
