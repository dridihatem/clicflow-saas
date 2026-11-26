<?php

use App\Enums\TaskAssigneeRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_task_assignees', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('project_task_id')->constrained('project_tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 32)->default(TaskAssigneeRole::Follower->value);
            $table->timestamps();

            $table->unique(['project_task_id', 'user_id']);
            $table->index(['user_id', 'role']);
        });

        if (Schema::hasColumn('project_tasks', 'assignee_user_id')) {
            $rows = DB::table('project_tasks')
                ->whereNotNull('assignee_user_id')
                ->select(['id', 'assignee_user_id'])
                ->get();

            $now = now();
            foreach ($rows as $row) {
                DB::table('project_task_assignees')->insert([
                    'id' => (string) \Illuminate\Support\Str::ulid(),
                    'project_task_id' => $row->id,
                    'user_id' => $row->assignee_user_id,
                    'role' => TaskAssigneeRole::Editor->value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('project_task_assignees');
    }
};
