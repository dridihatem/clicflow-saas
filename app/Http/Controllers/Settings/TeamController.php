<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use App\Services\TeamPermissionCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TeamController extends Controller
{
    public function index(Request $request): View
    {
        $actor = $request->user();
        abort_if(! $actor->hasRole('company_admin'), 403);
        $company = $actor->company;
        abort_if(! $company instanceof Company, 403);

        $users = User::query()
            ->where('company_id', $company->id)
            ->workspaceStaff()
            ->orderBy('name')
            ->with(['roles', 'permissions'])
            ->get();

        $adminCount = User::query()
            ->where('company_id', $company->id)
            ->workspaceStaff()
            ->whereHas('roles', fn ($q) => $q->where('name', 'company_admin'))
            ->count();

        return view('settings.team', [
            'users' => $users,
            'adminCount' => $adminCount,
            'optionalPermissions' => TeamPermissionCatalog::optionalForTeamMembers(),
            'roleOptions' => [
                'company_admin' => __('Company admin'),
                'team_member' => __('Team member'),
                'business_provider' => __('Business provider'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $actor = $request->user();
        abort_if(! $actor->hasRole('company_admin'), 403);
        $company = $actor->company;
        abort_if(! $company instanceof Company, 403);

        TeamPermissionCatalog::ensureOptionalPermissionsExist();
        $optional = TeamPermissionCatalog::optionalPermissionNames();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', 'string', Rule::in(['company_admin', 'team_member', 'business_provider'])],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in($optional)],
            'skills' => ['nullable', 'string', 'max:1000'],
        ]);

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'company_id' => $company->id,
            'email_verified_at' => now(),
            'skills_json' => $this->parseSkills($data['skills'] ?? null),
        ]);

        $user->assignRole($data['role']);
        $this->syncOptionalPermissions($user, $data['role'], $data['permissions'] ?? []);

        return redirect()->route('settings.team')->with('status', __('Team member added.'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();
        abort_if(! $actor->hasRole('company_admin'), 403);
        $this->assertSameCompany($actor, $user);
        abort_if($user->hasRole('client'), 404);

        TeamPermissionCatalog::ensureOptionalPermissionsExist();
        $optional = TeamPermissionCatalog::optionalPermissionNames();

        $data = $request->validate([
            'role' => ['required', 'string', Rule::in(['company_admin', 'team_member', 'business_provider'])],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in($optional)],
            'skills' => ['nullable', 'string', 'max:1000'],
        ]);

        $company = $actor->company;
        abort_if(! $company instanceof Company, 403);

        if ($user->hasRole('company_admin') && $data['role'] !== 'company_admin') {
            $adminCount = User::query()
                ->where('company_id', $company->id)
                ->workspaceStaff()
                ->whereHas('roles', fn ($q) => $q->where('name', 'company_admin'))
                ->count();
            if ($adminCount <= 1) {
                return redirect()->route('settings.team')->withErrors([
                    'role' => __('The workspace must keep at least one company admin.'),
                ]);
            }
        }

        $user->syncRoles([$data['role']]);
        $this->syncOptionalPermissions($user, $data['role'], $data['permissions'] ?? []);
        $user->forceFill([
            'skills_json' => $this->parseSkills($data['skills'] ?? null),
        ])->save();

        return redirect()->route('settings.team')->with('status', __('Team member updated.'));
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();
        abort_if(! $actor->hasRole('company_admin'), 403);
        $this->assertSameCompany($actor, $user);
        abort_if($user->hasRole('client'), 404);

        if ($user->id === $actor->id) {
            return redirect()->route('settings.team')->withErrors([
                'delete' => __('You cannot remove your own account from here.'),
            ]);
        }

        $company = $actor->company;
        abort_if(! $company instanceof Company, 403);

        if ($user->hasRole('company_admin')) {
            $adminCount = User::query()
                ->where('company_id', $company->id)
                ->workspaceStaff()
                ->whereHas('roles', fn ($q) => $q->where('name', 'company_admin'))
                ->count();
            if ($adminCount <= 1) {
                return redirect()->route('settings.team')->withErrors([
                    'delete' => __('You cannot remove the only company admin.'),
                ]);
            }
        }

        app(\App\Services\UserDeletionService::class)->delete($user);

        return redirect()->route('settings.team')->with('status', __('User removed from workspace.'));
    }

    /**
     * @param  list<string>  $permissions
     */
    private function syncOptionalPermissions(User $user, string $role, array $permissions): void
    {
        if ($role !== 'team_member') {
            $user->syncPermissions([]);

            return;
        }

        TeamPermissionCatalog::ensureOptionalPermissionsExist();

        $allowed = array_values(array_intersect(
            array_values(array_unique($permissions)),
            TeamPermissionCatalog::optionalPermissionNames(),
        ));

        $user->syncPermissions($allowed);
    }

    /**
     * @return list<string>|null
     */
    private function parseSkills(?string $raw): ?array
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        $skills = collect(preg_split('/[,;]+/', $raw) ?: [])
            ->map(static fn ($skill) => trim((string) $skill))
            ->filter()
            ->unique(static fn ($skill) => mb_strtolower($skill))
            ->take(20)
            ->values()
            ->all();

        return $skills === [] ? null : $skills;
    }

    private function assertSameCompany(User $actor, User $target): void
    {
        if ($target->company_id === null || $actor->company_id === null) {
            abort(404);
        }
        if ((string) $target->company_id !== (string) $actor->company_id) {
            abort(404);
        }
    }
}
