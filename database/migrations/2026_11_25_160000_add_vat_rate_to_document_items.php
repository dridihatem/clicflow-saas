<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-line VAT rate on invoice and quote lines. NULL = legacy line: the document keeps its single
 * stored VAT (existing documents compute and display exactly as before).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['invoice_items', 'proposal_items'] as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'vat_rate')) {
                Schema::table($table, function (Blueprint $t): void {
                    $t->decimal('vat_rate', 6, 3)->nullable()->after('total_amount');
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['invoice_items', 'proposal_items'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'vat_rate')) {
                Schema::table($table, function (Blueprint $t): void {
                    $t->dropColumn('vat_rate');
                });
            }
        }
    }
};
