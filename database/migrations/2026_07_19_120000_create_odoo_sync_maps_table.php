<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('odoo_sync_maps', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('entity_type', 32);
            $table->ulid('local_id');
            $table->string('odoo_model', 64);
            $table->unsignedBigInteger('odoo_id');
            $table->timestamp('last_synced_at')->nullable();
            $table->string('last_direction', 16)->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'entity_type', 'local_id'], 'odoo_sync_maps_local_unique');
            $table->unique(['company_id', 'odoo_model', 'odoo_id'], 'odoo_sync_maps_odoo_unique');
            $table->index(['company_id', 'entity_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('odoo_sync_maps');
    }
};
