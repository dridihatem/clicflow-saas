<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('name');
            $table->string('sku')->nullable();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('base_price_minor')->default(0);
            $table->string('currency', 3)->default('USD');
            $table->boolean('track_stock')->default(true);
            $table->boolean('is_active')->default(true);
            $table->string('external_shopify_id')->nullable();
            $table->string('external_woo_id')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'is_active']);
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('sku')->nullable();
            $table->string('color')->nullable();
            $table->string('size')->nullable();
            $table->unsignedBigInteger('price_minor')->nullable();
            $table->integer('stock_qty')->default(0);
            $table->string('external_shopify_id')->nullable();
            $table->string('external_woo_id')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'product_id']);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->integer('delta');
            $table->string('reason', 32);
            $table->string('reference_type')->nullable();
            $table->string('reference_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'product_variant_id']);
        });

        Schema::create('pos_sessions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->unsignedBigInteger('opening_cash_minor')->default(0);
            $table->unsignedBigInteger('closing_cash_minor')->nullable();
            $table->unsignedBigInteger('cash_sales_minor')->default(0);
            $table->unsignedBigInteger('card_sales_minor')->default(0);
            $table->timestamps();
        });

        Schema::create('commerce_orders', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->foreignUlid('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignUlid('pos_session_id')->nullable()->constrained('pos_sessions')->nullOnDelete();
            $table->string('source', 16)->default('manual');
            $table->string('status', 32)->default('draft');
            $table->string('currency', 3)->default('USD');
            $table->unsignedBigInteger('subtotal_minor')->default(0);
            $table->unsignedBigInteger('total_minor')->default(0);
            $table->string('payment_method', 32)->nullable();
            $table->timestamps();
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'source']);
        });

        Schema::create('commerce_order_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('commerce_order_id')->constrained('commerce_orders')->cascadeOnDelete();
            $table->foreignUlid('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('description');
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedBigInteger('unit_price_minor')->default(0);
            $table->unsignedBigInteger('total_minor')->default(0);
            $table->timestamps();
        });

        Schema::create('deliveries', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('commerce_order_id')->constrained('commerce_orders')->cascadeOnDelete();
            $table->string('status', 32)->default('pending');
            $table->string('carrier')->nullable();
            $table->string('tracking_number')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('commerce_integrations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('provider', 32);
            $table->text('credentials')->nullable();
            $table->json('settings')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->boolean('auto_push')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'provider']);
        });

        Schema::create('commerce_sync_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('provider', 32);
            $table->string('direction', 16);
            $table->string('status', 16);
            $table->string('summary')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'provider']);
        });

        Schema::create('real_estate_offers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->string('title');
            $table->string('property_type', 32);
            $table->string('transaction_type', 32);
            $table->unsignedBigInteger('price_minor')->default(0);
            $table->string('currency', 3)->default('USD');
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('status', 32)->default('draft');
            $table->text('description')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'property_type']);
            $table->index(['company_id', 'transaction_type']);
        });

        Schema::create('real_estate_offer_images', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('real_estate_offer_id')->constrained('real_estate_offers')->cascadeOnDelete();
            $table->string('path');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('real_estate_demands', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->json('property_types')->nullable();
            $table->json('transaction_types')->nullable();
            $table->unsignedBigInteger('budget_min_minor')->nullable();
            $table->unsignedBigInteger('budget_max_minor')->nullable();
            $table->string('currency', 3)->default('USD');
            $table->string('status', 32)->default('open');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('real_estate_contracts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('real_estate_offer_id')->nullable()->constrained('real_estate_offers')->nullOnDelete();
            $table->foreignUlid('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->string('contract_type', 32);
            $table->string('status', 32)->default('draft');
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->unsignedBigInteger('amount_minor')->default(0);
            $table->string('currency', 3)->default('USD');
            $table->string('document_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('real_estate_contracts');
        Schema::dropIfExists('real_estate_demands');
        Schema::dropIfExists('real_estate_offer_images');
        Schema::dropIfExists('real_estate_offers');
        Schema::dropIfExists('commerce_sync_logs');
        Schema::dropIfExists('commerce_integrations');
        Schema::dropIfExists('deliveries');
        Schema::dropIfExists('commerce_order_items');
        Schema::dropIfExists('commerce_orders');
        Schema::dropIfExists('pos_sessions');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');
    }
};
