<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Line title (bold) + description (small text) on invoice and quote lines.
 * Additive: a nullable `title` column. Invoice line descriptions are widened from VARCHAR(255)
 * to TEXT (same as proposal_items in 2026_09_25) so multi-line descriptions fit — no data change.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['invoice_items', 'proposal_items'] as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'title')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->string('title', 255)->nullable()->after('product_variant_id');
                });
            }
        }

        if (Schema::hasTable('invoice_items') && Schema::hasColumn('invoice_items', 'description')) {
            $driver = Schema::getConnection()->getDriverName();
            if ($driver === 'mysql') {
                DB::statement('ALTER TABLE invoice_items MODIFY description TEXT NOT NULL');
            } elseif ($driver === 'pgsql') {
                DB::statement('ALTER TABLE invoice_items ALTER COLUMN description TYPE TEXT');
            }
        }
    }

    public function down(): void
    {
        foreach (['invoice_items', 'proposal_items'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'title')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropColumn('title');
                });
            }
        }
    }
};
