<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prospects', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone', 60)->nullable();
            $table->string('whatsapp', 60)->nullable();
            $table->string('company_name')->nullable();
            $table->string('job_title')->nullable();
            $table->json('socials')->nullable();
            $table->string('source', 40)->default('import');
            $table->string('status', 40)->default('new');
            $table->timestamp('verified_at')->nullable();
            $table->string('verified_via', 40)->nullable();
            $table->foreignUlid('converted_client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'source']);
            $table->unique(['company_id', 'email']);
        });

        Schema::create('prospect_imports', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('filename')->nullable();
            $table->unsignedInteger('row_count')->default(0);
            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->json('errors')->nullable();
            $table->timestamps();
        });

        Schema::table('email_marketing_audience_contacts', function (Blueprint $table) {
            $table->foreignUlid('prospect_id')->nullable()->after('name')->constrained('prospects')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('email_marketing_audience_contacts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('prospect_id');
        });
        Schema::dropIfExists('prospect_imports');
        Schema::dropIfExists('prospects');
    }
};
