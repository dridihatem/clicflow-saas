<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Permanently deletes a tenant user and the rows that point at it but are not
 * covered by database cascades (roles/permissions, API tokens, sessions,
 * password reset tokens). Rows with ON DELETE CASCADE / SET NULL foreign keys
 * on users.id are handled by the database itself.
 */
class UserDeletionService
{
    public function delete(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $this->deleteMany([$user->id], [$user->email]);
        });
    }

    /**
     * Must be called inside a transaction.
     *
     * @param  list<int|string>  $userIds
     * @param  list<string>  $emails
     */
    public function deleteMany(array $userIds, array $emails = []): void
    {
        if ($userIds === []) {
            return;
        }

        $morph = (new User)->getMorphClass();

        foreach (['model_has_roles', 'model_has_permissions'] as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->where('model_type', $morph)->whereIn('model_id', $userIds)->delete();
            }
        }

        if (Schema::hasTable('personal_access_tokens')) {
            DB::table('personal_access_tokens')->where('tokenable_type', $morph)->whereIn('tokenable_id', $userIds)->delete();
        }

        if (Schema::hasTable('sessions')) {
            DB::table('sessions')->whereIn('user_id', $userIds)->delete();
        }

        $emails = array_values(array_filter($emails));
        if ($emails !== [] && Schema::hasTable('password_reset_tokens')) {
            DB::table('password_reset_tokens')->whereIn('email', $emails)->delete();
        }

        // FK cascades remove: project_user, project_task_assignees, social_accounts,
        // device_push_tokens, chat participants/messages, ai_conversations, support_tickets, ...
        // FK SET NULL keeps history rows (projects.created_by, audit_logs.user_id, ...).
        User::query()->whereIn('id', $userIds)->delete();

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
