<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Document-level discount (before VAT). subtotal_amount keeps meaning "net HT" (after discount),
 * so amount = subtotal + vat + stamp stays true everywhere; lines total = subtotal + discount_amount.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['invoices', 'proposals'] as $tableName) {
            if (Schema::hasColumn($tableName, 'discount_amount')) {
                continue;
            }
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('discount_type', 10)->nullable()->after('subtotal_amount');
                $table->decimal('discount_value', 14, 4)->nullable()->after('discount_type');
                $table->unsignedBigInteger('discount_amount')->default(0)->after('discount_value');
            });
        }
    }

    public function down(): void
    {
        foreach (['invoices', 'proposals'] as $tableName) {
            if (Schema::hasColumn($tableName, 'discount_amount')) {
                Schema::table($tableName, fn (Blueprint $table) => $table->dropColumn(['discount_type', 'discount_value', 'discount_amount']));
            }
        }
    }
};
