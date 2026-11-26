<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('project_studies')) {
            Schema::create('project_studies', function (Blueprint $table) {
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
                $table->softDeletes();

                $table->index(['company_id', 'status']);
                $table->index(['company_id', 'created_at']);
            });
        }

        if (Schema::hasTable('module_project_studies')) {
            $rows = DB::table('module_project_studies')->orderBy('created_at')->get();
            foreach ($rows as $row) {
                $exists = DB::table('project_studies')->where('id', $row->id)->exists();
                if ($exists) {
                    continue;
                }
                DB::table('project_studies')->insert([
                    'id' => $row->id,
                    'company_id' => $row->company_id,
                    'client_id' => $row->client_id,
                    'project_id' => $row->project_id,
                    'title' => $row->title,
                    'status' => $row->status ?? 'draft',
                    'currency' => $row->currency ?? 'USD',
                    'budget_minor' => $row->budget_minor,
                    'executive_summary' => $row->executive_summary,
                    'technical_study' => $row->technical_study,
                    'financial_study' => $row->financial_study,
                    'methodology' => $row->methodology,
                    'risks' => $row->risks,
                    'conclusion' => $row->conclusion,
                    'full_body' => $row->full_body,
                    'generated_at' => $row->generated_at,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                    'deleted_at' => null,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('project_studies');
    }
};
