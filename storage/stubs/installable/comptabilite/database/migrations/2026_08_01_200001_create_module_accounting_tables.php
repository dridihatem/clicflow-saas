<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('module_accounting_accounts')) {
            Schema::create('module_accounting_accounts', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->foreignUlid('company_id')->constrained()->cascadeOnDelete();
                $table->string('code', 32);
                $table->string('name');
                $table->string('type', 24);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['company_id', 'code']);
                $table->index(['company_id', 'type']);
            });
        }

        if (! Schema::hasTable('module_accounting_journals')) {
            Schema::create('module_accounting_journals', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->foreignUlid('company_id')->constrained()->cascadeOnDelete();
                $table->string('code', 16);
                $table->string('name');
                $table->string('kind', 24)->default('general');
                $table->timestamps();

                $table->unique(['company_id', 'code']);
            });
        }

        if (! Schema::hasTable('module_accounting_entries')) {
            Schema::create('module_accounting_entries', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->foreignUlid('company_id')->constrained()->cascadeOnDelete();
                $table->foreignUlid('journal_id')->constrained('module_accounting_journals')->cascadeOnDelete();
                $table->date('entry_date');
                $table->string('reference', 64)->nullable();
                $table->string('memo')->nullable();
                $table->string('status', 16)->default('draft');
                $table->string('currency', 3)->default('USD');
                $table->timestamps();

                $table->index(['company_id', 'entry_date']);
                $table->index(['company_id', 'status']);
            });
        }

        if (! Schema::hasTable('module_accounting_entry_lines')) {
            Schema::create('module_accounting_entry_lines', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->foreignUlid('company_id')->constrained()->cascadeOnDelete();
                $table->foreignUlid('entry_id')->constrained('module_accounting_entries')->cascadeOnDelete();
                $table->foreignUlid('account_id')->constrained('module_accounting_accounts')->restrictOnDelete();
                $table->string('label')->nullable();
                $table->unsignedBigInteger('debit_minor')->default(0);
                $table->unsignedBigInteger('credit_minor')->default(0);
                $table->timestamps();

                $table->index(['company_id', 'account_id']);
            });
        }

        if (! Schema::hasTable('module_project_studies')) {
            Schema::create('module_project_studies', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->foreignUlid('company_id')->constrained()->cascadeOnDelete();
                $table->foreignUlid('client_id')->nullable()->constrained('clients')->nullOnDelete();
                $table->foreignUlid('project_id')->nullable()->constrained('projects')->nullOnDelete();
                $table->string('title');
                $table->string('status', 24)->default('draft');
                $table->string('currency', 3)->default('USD');
                $table->unsignedBigInteger('budget_minor')->nullable();
                $table->longText('executive_summary')->nullable();
                $table->longText('technical_study')->nullable();
                $table->longText('financial_study')->nullable();
                $table->longText('methodology')->nullable();
                $table->longText('risks')->nullable();
                $table->longText('conclusion')->nullable();
                $table->longText('full_body')->nullable();
                $table->timestamp('generated_at')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('module_project_studies');
        Schema::dropIfExists('module_accounting_entry_lines');
        Schema::dropIfExists('module_accounting_entries');
        Schema::dropIfExists('module_accounting_journals');
        Schema::dropIfExists('module_accounting_accounts');
    }
};
