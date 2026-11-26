<?php

use App\Services\TeamPermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::query()->firstOrCreate(['name' => 'team_member', 'guard_name' => 'web']);

        foreach (TeamPermissionCatalog::teamMemberDefaults() as $name) {
            Permission::query()->firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        foreach (TeamPermissionCatalog::optionalPermissionNames() as $name) {
            Permission::query()->firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        // Baseline only — company admins can grant extras per user in Team settings.
        $role->syncPermissions(TeamPermissionCatalog::teamMemberDefaults());
    }

    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::query()->where('name', 'team_member')->where('guard_name', 'web')->first();
        if (! $role) {
            return;
        }

        $role->syncPermissions([
            'workspace.view_dashboard',
            'workspace.manage_projects',
            'workspace.manage_inquiries',
            'workspace.manage_invoices',
            'workspace.manage_clients',
            'workspace.manage_commerce',
            'workspace.manage_real_estate',
        ]);
    }
};
