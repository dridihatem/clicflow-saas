<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_prospects', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('name');
            $table->string('phone', 60)->nullable();
            $table->string('email')->nullable();
            $table->string('campaign')->nullable();
            $table->string('source', 80)->nullable();
            $table->string('status', 40)->default('nouveau');
            $table->text('comment')->nullable();
            $table->date('captured_on')->nullable();
            $table->timestamp('appointment_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'client_id', 'status']);
            $table->index(['company_id', 'client_id', 'appointment_at']);
            $table->index(['company_id', 'client_id', 'captured_on']);
        });

        Schema::create('client_prospect_comments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('client_prospect_id')->constrained('client_prospects')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kind', 32)->default('comment');
            $table->text('body');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'client_prospect_id', 'created_at'], 'cp_comments_company_prospect_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_prospect_comments');
        Schema::dropIfExists('client_prospects');
    }
};
