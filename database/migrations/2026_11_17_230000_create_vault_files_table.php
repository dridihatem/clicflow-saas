<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Coffre-fort : fichiers partagés de l'espace (entreprise, clients, projets, équipe, prestataires).
 * Migration purement additive.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('vault_files')) {
            Schema::create('vault_files', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->foreignUlid('company_id')->constrained()->cascadeOnDelete();
                $table->foreignUlid('project_id')->nullable()->constrained('projects')->nullOnDelete();
                $table->foreignUlid('client_id')->nullable()->constrained('clients')->nullOnDelete();
                $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('uploader_type', 20)->default('staff'); // staff | client | provider
                $table->string('folder', 120)->nullable();
                $table->string('original_name');
                $table->string('extension', 16)->nullable();
                $table->string('disk', 32)->default('local');
                $table->string('path');
                $table->string('mime', 191)->nullable();
                $table->unsignedBigInteger('size')->default(0);
                $table->text('description')->nullable();
                $table->boolean('share_client')->default(false);
                $table->boolean('share_project')->default(false);
                $table->boolean('share_team')->default(false);
                $table->boolean('share_providers')->default(false);
                $table->timestamp('client_notified_at')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['company_id', 'created_at']);
                $table->index(['company_id', 'client_id']);
                $table->index(['company_id', 'project_id']);
                $table->index(['company_id', 'folder']);
            });
        }

        if (! Schema::hasTable('vault_file_user')) {
            Schema::create('vault_file_user', function (Blueprint $table) {
                $table->foreignUlid('vault_file_id')->constrained('vault_files')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->timestamps();
                $table->primary(['vault_file_id', 'user_id']);
            });
        }
    }

    public function down(): void
    {
        // Additive only: tables are kept on rollback to avoid data loss.
    }
};
