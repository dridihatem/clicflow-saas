<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'tax_rate')) {
                $table->decimal('tax_rate', 5, 2)->nullable()->after('base_price_minor');
            }
            if (! Schema::hasColumn('products', 'tax_rate_2')) {
                $table->decimal('tax_rate_2', 5, 2)->nullable()->after('tax_rate');
            }
            if (! Schema::hasColumn('products', 'category')) {
                $table->string('category', 100)->nullable()->after('tax_rate_2');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            foreach (['category', 'tax_rate_2', 'tax_rate'] as $col) {
                if (Schema::hasColumn('products', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
