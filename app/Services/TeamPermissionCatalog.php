<?php

namespace App\Services;

/**
 * Optional workspace permissions a company admin can grant to team members.
 */
class TeamPermissionCatalog
{
    /**
     * Baseline permissions always granted by the team_member role.
     *
     * @return list<string>
     */
    public static function teamMemberDefaults(): array
    {
        return [
            'workspace.view_dashboard',
            'workspace.manage_projects',
        ];
    }

    /**
     * Extra permissions admins may toggle per team member (stored as direct permissions).
     *
     * @return array<string, string> permission => translation key
     */
    public static function optionalForTeamMembers(): array
    {
        return [
            'workspace.manage_clients' => 'Clients',
            'workspace.manage_inquiries' => 'Inquiries',
            'workspace.manage_invoices' => 'Invoices & quotes',
            'workspace.view_analytics' => 'Analytics & reports',
            'workspace.manage_providers' => 'Providers',
            'workspace.manage_commerce' => 'Commerce & POS',
            'workspace.manage_real_estate' => 'Real estate',
            'workspace.manage_hr' => 'HR admin (employees, payroll, approvals)',
            'workspace.hr_self_service' => 'My HR (leave, salary advances, payslips)',
        ];
    }

    /**
     * Ensure optional permission rows exist (safe to call before syncPermissions).
     */
    public static function ensureOptionalPermissionsExist(): void
    {
        foreach (self::optionalPermissionNames() as $name) {
            \Spatie\Permission\Models\Permission::findOrCreate($name, 'web');
        }
    }

    /**
     * @return list<string>
     */
    public static function optionalPermissionNames(): array
    {
        return array_keys(self::optionalForTeamMembers());
    }
}
