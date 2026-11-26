<?php

namespace App\Services\Vault;

use App\Models\Client;
use App\Models\Project;
use App\Models\Provider;
use App\Models\User;
use App\Models\VaultFile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Règles d'accès du Coffre-fort.
 *
 * - Admin entreprise (ou permission workspace.access_vault) : tout le coffre de l'entreprise.
 * - Membre d'équipe : ses dépôts, fichiers partagés avec toute l'équipe, avec lui nommément,
 *   ou liés à un projet dont il est membre (si « membres du projet » est coché).
 * - Client : fichiers partagés avec lui (client_id) ou avec ses projets, plus ses propres dépôts.
 * - Prestataire : fichiers partagés avec les prestataires sur ses projets, plus ses propres dépôts.
 */
class VaultAccessService
{
    /** @var array<int, ?Client> */
    private array $clientCache = [];

    /** @var array<int, ?Provider> */
    private array $providerCache = [];

    public function isManager(User $user): bool
    {
        return $user->hasRole('company_admin') || $user->can('workspace.access_vault');
    }

    public function isStaff(User $user): bool
    {
        return $user->hasAnyRole(['company_admin', 'team_member']);
    }

    public function clientFor(User $user): ?Client
    {
        if (! array_key_exists($user->id, $this->clientCache)) {
            $this->clientCache[$user->id] = Client::query()->withoutGlobalScope('tenant')
                ->where('company_id', $user->company_id)
                ->where('user_id', $user->id)
                ->first();
        }

        return $this->clientCache[$user->id];
    }

    public function providerFor(User $user): ?Provider
    {
        if (! array_key_exists($user->id, $this->providerCache)) {
            $this->providerCache[$user->id] = Provider::query()->withoutGlobalScope('tenant')
                ->where('company_id', $user->company_id)
                ->where('user_id', $user->id)
                ->first();
        }

        return $this->providerCache[$user->id];
    }

    /** Area the user browses the vault from: company | portal | provider. */
    public function areaFor(User $user): string
    {
        if ($this->isStaff($user)) {
            return 'company';
        }
        if ($user->hasRole('business_provider')) {
            return 'provider';
        }

        return 'portal';
    }

    /** @return list<string> */
    public function memberProjectIds(User $user): array
    {
        return DB::table('project_user')
            ->join('projects', 'projects.id', '=', 'project_user.project_id')
            ->where('projects.company_id', $user->company_id)
            ->where('project_user.user_id', $user->id)
            ->pluck('project_user.project_id')->map(fn ($v) => (string) $v)->all();
    }

    /** Files the user may see, always restricted to their company. */
    public function visibleQuery(User $user, ?string $area = null): Builder
    {
        $area ??= $this->areaFor($user);
        $q = VaultFile::query()->withoutGlobalScopes()
            ->whereNull('vault_files.deleted_at')
            ->where('vault_files.company_id', $user->company_id);

        if ($area === 'company') {
            if (! $this->isStaff($user)) {
                return $q->whereRaw('1 = 0');
            }
            if ($this->isManager($user)) {
                return $q;
            }
            $projectIds = $this->memberProjectIds($user);

            return $q->where(function (Builder $w) use ($user, $projectIds) {
                $w->where('uploaded_by', $user->id)
                    ->orWhere('share_team', true)
                    ->orWhereExists(function ($sub) use ($user) {
                        $sub->select(DB::raw(1))->from('vault_file_user')
                            ->whereColumn('vault_file_user.vault_file_id', 'vault_files.id')
                            ->where('vault_file_user.user_id', $user->id);
                    });
                if ($projectIds !== []) {
                    $w->orWhere(fn (Builder $p) => $p->where('share_project', true)->whereIn('project_id', $projectIds));
                }
            });
        }

        if ($area === 'portal') {
            $client = $this->clientFor($user);
            if (! $client) {
                return $q->where('uploaded_by', $user->id);
            }
            $projectIds = Project::query()->withoutGlobalScope('tenant')
                ->where('company_id', $user->company_id)->where('client_id', $client->id)
                ->pluck('id')->all();

            return $q->where(function (Builder $w) use ($user, $client, $projectIds) {
                $w->where('uploaded_by', $user->id)
                    ->orWhere(function (Builder $s) use ($client, $projectIds) {
                        $s->where('share_client', true)->where(function (Builder $t) use ($client, $projectIds) {
                            $t->where('client_id', $client->id);
                            if ($projectIds !== []) {
                                $t->orWhereIn('project_id', $projectIds);
                            }
                        });
                    });
            });
        }

        if ($area === 'provider') {
            $provider = $this->providerFor($user);
            $projectIds = $provider ? $this->providerProjectIds($provider) : [];

            return $q->where(function (Builder $w) use ($user, $projectIds) {
                $w->where('uploaded_by', $user->id);
                if ($projectIds !== []) {
                    $w->orWhere(fn (Builder $p) => $p->where('share_providers', true)->whereIn('project_id', $projectIds));
                }
            });
        }

        return $q->whereRaw('1 = 0');
    }

    /** @return list<string> */
    public function providerProjectIds(Provider $provider): array
    {
        return Project::query()->withoutGlobalScope('tenant')
            ->where('company_id', $provider->company_id)
            ->where('provider_id', $provider->id)
            ->pluck('id')->map(fn ($v) => (string) $v)->all();
    }

    public function canView(User $user, VaultFile $file, ?string $area = null): bool
    {
        if ((string) $file->company_id !== (string) $user->company_id || $file->trashed()) {
            return false;
        }

        return $this->visibleQuery($user, $area)->whereKey($file->getKey())->exists();
    }

    public function canDelete(User $user, VaultFile $file): bool
    {
        if ((string) $file->company_id !== (string) $user->company_id) {
            return false;
        }

        return (int) $file->uploaded_by === (int) $user->id || ($this->isStaff($user) && $this->isManager($user));
    }

    /** Edit visibility / folder: managers, or the staff uploader. */
    public function canEditSharing(User $user, VaultFile $file): bool
    {
        if ((string) $file->company_id !== (string) $user->company_id || ! $this->isStaff($user)) {
            return false;
        }

        return $this->isManager($user) || (int) $file->uploaded_by === (int) $user->id;
    }

    /** Projects the user may attach an upload to. */
    public function uploadableProjects(User $user, ?string $area = null): Collection
    {
        $area ??= $this->areaFor($user);
        $q = Project::query()->withoutGlobalScope('tenant')->where('company_id', $user->company_id)->orderBy('title');

        if ($area === 'company') {
            if (! $this->isStaff($user)) {
                return collect();
            }
            if (! $this->isManager($user)) {
                // Team members can only attach uploads to projects they belong to.
                $q->whereIn('id', $this->memberProjectIds($user));
            }

            return $q->get(['id', 'title', 'client_id', 'provider_id']);
        }
        if ($area === 'portal') {
            $client = $this->clientFor($user);

            return $client ? $q->where('client_id', $client->id)->get(['id', 'title', 'client_id', 'provider_id']) : collect();
        }
        if ($area === 'provider') {
            $provider = $this->providerFor($user);

            return $provider ? $q->where('provider_id', $provider->id)->get(['id', 'title', 'client_id', 'provider_id']) : collect();
        }

        return collect();
    }

    /** @return list<string> human badges for a file */
    public function badges(VaultFile $file): array
    {
        $b = [];
        if ($file->share_client) {
            $b[] = 'client';
        }
        if ($file->share_project && $file->project_id) {
            $b[] = 'project';
        }
        if ($file->share_team) {
            $b[] = 'team';
        }
        if ($file->share_providers && $file->project_id) {
            $b[] = 'providers';
        }
        if ($file->relationLoaded('sharedUsers') ? $file->sharedUsers->isNotEmpty() : $file->sharedUsers()->exists()) {
            $b[] = 'members';
        }

        return $b === [] ? ['internal'] : $b;
    }
}
