<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_module_entitlements', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('module_key', 64);
            $table->string('source', 32)->default('marketplace');
            $table->ulid('marketplace_order_id')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'module_key']);
            $table->index(['module_key']);
            $table->index(['marketplace_order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_module_entitlements');
    }
};
