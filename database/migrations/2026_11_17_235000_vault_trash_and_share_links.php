<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Coffre-fort : corbeille (auteur de la suppression) + liens de partage publics. Additive. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('vault_files') && ! Schema::hasColumn('vault_files', 'deleted_by')) {
            Schema::table('vault_files', function (Blueprint $table) {
                $table->foreignId('deleted_by')->nullable()->after('deleted_at')->constrained('users')->nullOnDelete();
                $table->index(['company_id', 'deleted_at']);
            });
        }

        if (! Schema::hasTable('vault_file_shares')) {
            Schema::create('vault_file_shares', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->foreignUlid('company_id')->constrained()->cascadeOnDelete();
                $table->foreignUlid('vault_file_id')->constrained('vault_files')->cascadeOnDelete();
                $table->string('token', 80)->unique();
                $table->string('password_hash')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->unsignedInteger('max_downloads')->nullable();
                $table->unsignedInteger('download_count')->default(0);
                $table->unsignedInteger('view_count')->default(0);
                $table->boolean('allow_preview')->default(true);
                $table->string('label', 120)->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('revoked_at')->nullable();
                $table->timestamp('last_accessed_at')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'vault_file_id']);
            });
        }
    }

    public function down(): void
    {
        // Additive only.
    }
};
