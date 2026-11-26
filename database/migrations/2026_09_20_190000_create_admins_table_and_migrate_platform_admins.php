<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('locale', 16)->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        if (! Schema::hasTable('model_has_roles') || ! Schema::hasTable('roles')) {
            return;
        }

        $role = Role::query()->where('name', 'platform_admin')->where('guard_name', 'web')->first();
        if ($role === null) {
            return;
        }

        $userIds = DB::table('model_has_roles')
            ->where('role_id', $role->id)
            ->where('model_type', (new User)->getMorphClass())
            ->pluck('model_id');

        $now = now();

        foreach ($userIds as $userId) {
            $user = DB::table('users')->where('id', $userId)->first();
            if ($user === null) {
                continue;
            }

            DB::table('admins')->updateOrInsert(
                ['email' => $user->email],
                [
                    'name' => $user->name,
                    'password' => $user->password,
                    'locale' => $user->locale ?? null,
                    'email_verified_at' => $user->email_verified_at ?? $now,
                    'remember_token' => $user->remember_token,
                    'created_at' => $user->created_at ?? $now,
                    'updated_at' => $now,
                ],
            );

            DB::table('model_has_roles')
                ->where('role_id', $role->id)
                ->where('model_type', (new User)->getMorphClass())
                ->where('model_id', $userId)
                ->delete();

            $remainingRoles = DB::table('model_has_roles')
                ->where('model_type', (new User)->getMorphClass())
                ->where('model_id', $userId)
                ->count();

            if ($user->company_id === null && $remainingRoles === 0) {
                DB::table('users')->where('id', $userId)->delete();
            }
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('admins');
    }
};
