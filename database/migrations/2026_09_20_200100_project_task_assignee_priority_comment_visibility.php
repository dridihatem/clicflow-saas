<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_tasks', function (Blueprint $table) {
            if (! Schema::hasColumn('project_tasks', 'assignee_user_id')) {
                $table->foreignId('assignee_user_id')->nullable()->after('project_id')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('project_tasks', 'priority')) {
                $table->string('priority', 32)->nullable()->default('medium')->after('status');
            }
        });

        Schema::table('project_task_comments', function (Blueprint $table) {
            if (! Schema::hasColumn('project_task_comments', 'visibility')) {
                $table->string('visibility', 32)->default('shared')->after('is_client');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'skills_json')) {
                $table->json('skills_json')->nullable()->after('appearance_json');
            }
        });
    }

    public function down(): void
    {
        Schema::table('project_tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assignee_user_id');
            $table->dropColumn('priority');
        });

        Schema::table('project_task_comments', function (Blueprint $table) {
            $table->dropColumn('visibility');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('skills_json');
        });
    }
};
