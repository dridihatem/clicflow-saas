<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'kind')) {
                $table->string('kind', 16)->default('product')->after('name'); // product|service
            }
            if (! Schema::hasColumn('products', 'unit')) {
                $table->string('unit', 32)->nullable()->after('currency');
            }
        });

        Schema::table('proposal_items', function (Blueprint $table) {
            if (! Schema::hasColumn('proposal_items', 'product_id')) {
                $table->ulid('product_id')->nullable()->after('proposal_id');
                $table->ulid('product_variant_id')->nullable()->after('product_id');
                $table->string('unit', 32)->nullable()->after('description');
            }
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            if (! Schema::hasColumn('invoice_items', 'product_id')) {
                $table->ulid('product_id')->nullable()->after('invoice_id');
                $table->ulid('product_variant_id')->nullable()->after('product_id');
                $table->string('unit', 32)->nullable()->after('description');
            }
        });

        Schema::table('proposals', function (Blueprint $table) {
            if (! Schema::hasColumn('proposals', 'advance_amount_minor')) {
                $table->unsignedBigInteger('advance_amount_minor')->nullable()->after('amount');
                $table->decimal('advance_percent', 5, 2)->nullable()->after('advance_amount_minor');
                $table->json('payment_terms_json')->nullable()->after('advance_percent');
                $table->ulid('deposit_invoice_id')->nullable()->after('payment_terms_json');
                $table->ulid('remainder_invoice_id')->nullable()->after('deposit_invoice_id');
            }
        });

        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('invoices', 'invoice_kind')) {
                $table->string('invoice_kind', 32)->default('standard')->after('status'); // standard|deposit|remainder
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'kind')) {
                $table->dropColumn('kind');
            }
            if (Schema::hasColumn('products', 'unit')) {
                $table->dropColumn('unit');
            }
        });

        Schema::table('proposal_items', function (Blueprint $table) {
            foreach (['product_id', 'product_variant_id', 'unit'] as $col) {
                if (Schema::hasColumn('proposal_items', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            foreach (['product_id', 'product_variant_id', 'unit'] as $col) {
                if (Schema::hasColumn('invoice_items', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('proposals', function (Blueprint $table) {
            foreach (['advance_amount_minor', 'advance_percent', 'payment_terms_json', 'deposit_invoice_id', 'remainder_invoice_id'] as $col) {
                if (Schema::hasColumn('proposals', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'invoice_kind')) {
                $table->dropColumn('invoice_kind');
            }
        });
    }
};
