<?php

namespace App\Http\Controllers\Vault;

use App\Http\Controllers\Controller;
use App\Mail\VaultFileSharedMail;
use App\Models\Client;
use App\Models\Company;
use App\Models\ProjectFile;
use App\Models\User;
use App\Models\VaultFile;
use App\Services\AuditLogService;
use App\Services\TenantSmtpMailer;
use App\Services\Vault\VaultAccessService;
use App\Services\Vault\VaultStorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/** Coffre-fort — espace entreprise (admins & équipe). Sous-classes : portail client, prestataire. */
class VaultController extends Controller
{
    protected string $area = 'company';

    public function __construct(
        protected VaultAccessService $access,
        protected VaultStorageService $storage,
    ) {}

    protected function routePrefix(): string
    {
        return match ($this->area) {
            'portal' => 'portal.vault.',
            'provider' => 'provider.vault.',
            default => 'vault.',
        };
    }

    protected function guardArea(User $user): void
    {
        $ok = match ($this->area) {
            'company' => $this->access->isStaff($user),
            'portal' => $user->hasRole('client'),
            'provider' => $user->hasRole('business_provider'),
            default => false,
        };
        abort_unless($ok && filled($user->company_id), 403);
    }

    protected function findFile(User $user, string $id): VaultFile
    {
        $file = VaultFile::query()->withoutGlobalScopes()
            ->where('company_id', $user->company_id)
            ->whereKey($id)
            ->firstOrFail();
        abort_unless($this->access->canView($user, $file, $this->area), 403);

        return $file;
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $this->guardArea($user);

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'client_id' => (string) $request->query('client_id', ''),
            'project_id' => (string) $request->query('project_id', ''),
            'type' => (string) $request->query('type', ''),
            'uploader' => (string) $request->query('uploader', ''),
            'folder' => (string) $request->query('folder', ''),
            'from' => (string) $request->query('from', ''),
            'to' => (string) $request->query('to', ''),
        ];

        $base = $this->access->visibleQuery($user, $this->area);
        $folders = (clone $base)->whereNotNull('folder')->where('folder', '!=', '')
            ->distinct()->orderBy('folder')->pluck('folder')->all();

        $q = $base->with(['project:id,title,client_id', 'client:id,name,company_name', 'uploader:id,name', 'sharedUsers:id,name']);
        if ($filters['q'] !== '') {
            $term = '%'.str_replace(['%', '_'], ['\%', '\_'], $filters['q']).'%';
            $q->where(fn ($w) => $w->where('original_name', 'like', $term)
                ->orWhere('description', 'like', $term)
                ->orWhere('folder', 'like', $term));
        }
        if ($filters['client_id'] !== '' && $this->area === 'company') {
            $cid = $filters['client_id'];
            $q->where(fn ($w) => $w->where('client_id', $cid)
                ->orWhereIn('project_id', fn ($s) => $s->select('id')->from('projects')->where('client_id', $cid)));
        }
        if ($filters['project_id'] !== '') {
            $q->where('project_id', $filters['project_id']);
        }
        if ($filters['type'] !== '') {
            $map = [
                'image' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
                'pdf' => ['pdf'],
                'document' => ['doc', 'docx', 'odt', 'rtf', 'txt'],
                'sheet' => ['xls', 'xlsx', 'ods', 'csv'],
                'presentation' => ['ppt', 'pptx', 'odp'],
                'archive' => ['zip'],
            ];
            if (isset($map[$filters['type']])) {
                $q->whereIn('extension', $map[$filters['type']]);
            }
        }
        if (in_array($filters['uploader'], [VaultFile::UPLOADER_STAFF, VaultFile::UPLOADER_CLIENT, VaultFile::UPLOADER_PROVIDER], true)) {
            $q->where('uploader_type', $filters['uploader']);
        }
        if ($filters['folder'] !== '') {
            $q->where('folder', $filters['folder']);
        }
        foreach (['from' => '>=', 'to' => '<='] as $key => $op) {
            if ($filters[$key] !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters[$key])) {
                $q->whereDate('vault_files.created_at', $op, $filters[$key]);
            }
        }

        $files = $q->latest('vault_files.created_at')->paginate(25)->withQueryString();

        $projects = $this->access->uploadableProjects($user, $this->area);
        $clients = collect();
        $members = collect();
        $legacy = collect();
        if ($this->area === 'company') {
            $clients = Client::query()->withoutGlobalScope('tenant')->where('company_id', $user->company_id)
                ->orderBy('name')->get(['id', 'name', 'company_name', 'email']);
            $members = User::query()->where('company_id', $user->company_id)
                ->whereHas('roles', fn ($r) => $r->whereIn('name', ['company_admin', 'team_member']))
                ->where('id', '!=', $user->id)->orderBy('name')->get(['id', 'name', 'email']);
            if ($this->access->isManager($user)) {
                $legacy = ProjectFile::query()->withoutGlobalScopes()
                    ->where('company_id', $user->company_id)->where('is_vault', true)
                    ->with(['project' => fn ($p) => $p->withoutGlobalScopes()->select('id', 'title')])
                    ->latest()->limit(50)->get();
            }
        }

        $stats = [
            'total' => (clone $this->access->visibleQuery($user, $this->area))->count(),
            'size' => (int) (clone $this->access->visibleQuery($user, $this->area))->sum('size'),
            'shared_client' => (clone $this->access->visibleQuery($user, $this->area))->where('share_client', true)->count(),
            'from_clients' => (clone $this->access->visibleQuery($user, $this->area))->where('uploader_type', VaultFile::UPLOADER_CLIENT)->count(),
        ];

        return view('vault.index', [
            'area' => $this->area,
            'rp' => $this->routePrefix(),
            'files' => $files,
            'filters' => $filters,
            'folders' => $folders,
            'projects' => $projects,
            'clients' => $clients,
            'members' => $members,
            'legacy' => $legacy,
            'stats' => $stats,
            'access' => $this->access,
            'maxKb' => $this->storage->maxFileKb(),
            'allowed' => VaultStorageService::ALLOWED_EXTENSIONS,
            'openUpload' => $request->boolean('upload'),
            'trashCount' => $this->area === 'company' && $this->access->isManager($user)
                ? VaultFile::query()->withoutGlobalScopes()->onlyTrashed()->where('company_id', $user->company_id)->count()
                : 0,
            'linksFile' => $this->linksFileFor($request, $user),
            'createdShare' => session('vault_share_created'),
            'editFile' => $this->area === 'company' && $request->filled('edit')
                ? $files->getCollection()->firstWhere('id', (string) $request->query('edit'))
                : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $this->guardArea($user);

        $projectIds = $this->access->uploadableProjects($user, $this->area)->pluck('id')->map(fn ($v) => (string) $v)->all();
        $rules = [
            'files' => ['required', 'array', 'min:1', 'max:10'],
            'files.*' => $this->storage->fileRules(),
            'project_id' => ['nullable', 'string', Rule::in($projectIds)],
            'folder' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
        if ($this->area === 'company') {
            $rules += [
                'client_id' => ['nullable', 'string', Rule::exists('clients', 'id')->where('company_id', $user->company_id)],
                'share_client' => ['nullable', 'boolean'],
                'share_project' => ['nullable', 'boolean'],
                'share_team' => ['nullable', 'boolean'],
                'share_providers' => ['nullable', 'boolean'],
                'notify_client' => ['nullable', 'boolean'],
                'user_ids' => ['nullable', 'array'],
                'user_ids.*' => ['integer', Rule::exists('users', 'id')->where('company_id', $user->company_id)],
            ];
        }
        $data = $request->validate($rules, [], [
            'files' => __('vault_field_files'),
            'files.*' => __('vault_field_file'),
            'project_id' => __('Project'),
            'client_id' => __('Client'),
        ]);

        $projectId = filled($data['project_id'] ?? null) ? (string) $data['project_id'] : null;
        $project = $projectId ? $this->access->uploadableProjects($user, $this->area)->firstWhere('id', $projectId) : null;
        $attrs = ['project_id' => $projectId, 'folder' => $this->cleanFolder($data['folder'] ?? null), 'description' => filled($data['description'] ?? null) ? trim($data['description']) : null];

        if ($this->area === 'company') {
            $clientId = filled($data['client_id'] ?? null) ? (string) $data['client_id'] : ($project?->client_id ? (string) $project->client_id : null);
            $attrs += [
                'uploader_type' => VaultFile::UPLOADER_STAFF,
                'client_id' => $clientId,
                'share_client' => (bool) ($data['share_client'] ?? false) && $clientId !== null,
                'share_project' => (bool) ($data['share_project'] ?? false) && $projectId !== null,
                'share_team' => (bool) ($data['share_team'] ?? false),
                'share_providers' => (bool) ($data['share_providers'] ?? false) && $projectId !== null,
            ];
        } elseif ($this->area === 'portal') {
            $client = $this->access->clientFor($user);
            $attrs += [
                'uploader_type' => VaultFile::UPLOADER_CLIENT,
                'client_id' => $client?->id,
                'share_client' => $client !== null,
                'share_project' => $projectId !== null,
                // Without a project, the client's deposit is visible to the whole company team.
                'share_team' => $projectId === null,
                'share_providers' => false,
            ];
        } else {
            $attrs += [
                'uploader_type' => VaultFile::UPLOADER_PROVIDER,
                'client_id' => null,
                'share_client' => false,
                'share_project' => $projectId !== null,
                'share_team' => false,
                'share_providers' => $projectId !== null,
            ];
        }

        $created = [];
        foreach ($request->file('files', []) as $upload) {
            $file = $this->storage->store($upload, $user, $attrs);
            if ($this->area === 'company' && ! empty($data['user_ids'])) {
                $file->sharedUsers()->sync(array_map('intval', $data['user_ids']));
            }
            app(AuditLogService::class)->record('vault.uploaded', $file, [
                'name' => $file->original_name,
                'area' => $this->area,
                'project_id' => $file->project_id,
                'client_id' => $file->client_id,
            ], (string) $user->company_id, $user->id);
            $created[] = $file;
        }

        $notified = false;
        if ($this->area === 'company' && ($data['notify_client'] ?? false) && ($attrs['share_client'] ?? false) && $created !== []) {
            $notified = $this->notifyClient($user, $created[0], count($created));
        }

        $msg = trans_choice('vault_flash_uploaded', count($created), ['count' => count($created)]);
        if ($notified) {
            $msg .= ' '.__('vault_flash_notified');
        }

        return redirect()->route($this->routePrefix().'index')->with('status', $msg);
    }

    public function update(Request $request, string $file): RedirectResponse
    {
        $user = $request->user();
        $this->guardArea($user);
        abort_unless($this->area === 'company', 404);
        $vault = $this->findFile($user, $file);
        abort_unless($this->access->canEditSharing($user, $vault), 403);

        $projectIds = $this->access->uploadableProjects($user, $this->area)->pluck('id')->map(fn ($v) => (string) $v)->all();
        if ($vault->project_id) {
            $projectIds[] = (string) $vault->project_id;
        }
        $data = $request->validate([
            'project_id' => ['nullable', 'string', Rule::in($projectIds)],
            'client_id' => ['nullable', 'string', Rule::exists('clients', 'id')->where('company_id', $user->company_id)],
            'folder' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'share_client' => ['nullable', 'boolean'],
            'share_project' => ['nullable', 'boolean'],
            'share_team' => ['nullable', 'boolean'],
            'share_providers' => ['nullable', 'boolean'],
            'notify_client' => ['nullable', 'boolean'],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['integer', Rule::exists('users', 'id')->where('company_id', $user->company_id)],
        ]);
        $projectId = filled($data['project_id'] ?? null) ? (string) $data['project_id'] : null;
        $clientId = filled($data['client_id'] ?? null) ? (string) $data['client_id'] : null;
        if (! $clientId && $projectId) {
            $clientId = \App\Models\Project::query()->withoutGlobalScope('tenant')->where('company_id', $user->company_id)->whereKey($projectId)->value('client_id');
            $clientId = $clientId ? (string) $clientId : null;
        }
        $wasShared = $vault->share_client;
        $vault->forceFill([
            'project_id' => $projectId,
            'client_id' => $clientId,
            'folder' => $this->cleanFolder($data['folder'] ?? null),
            'description' => filled($data['description'] ?? null) ? trim($data['description']) : null,
            'share_client' => (bool) ($data['share_client'] ?? false) && $clientId !== null,
            'share_project' => (bool) ($data['share_project'] ?? false) && $projectId !== null,
            'share_team' => (bool) ($data['share_team'] ?? false),
            'share_providers' => (bool) ($data['share_providers'] ?? false) && $projectId !== null,
        ])->save();
        $vault->sharedUsers()->sync(array_map('intval', $data['user_ids'] ?? []));

        app(AuditLogService::class)->record('vault.sharing_updated', $vault, [
            'name' => $vault->original_name,
            'badges' => $this->access->badges($vault->fresh('sharedUsers')),
        ], (string) $user->company_id, $user->id);

        $msg = __('vault_flash_updated');
        if (($data['notify_client'] ?? false) && $vault->share_client && ! $wasShared && $this->notifyClient($user, $vault, 1)) {
            $msg .= ' '.__('vault_flash_notified');
        }

        return redirect()->route($this->routePrefix().'index')->with('status', $msg);
    }

    public function download(Request $request, string $file): StreamedResponse
    {
        $user = $request->user();
        $this->guardArea($user);
        $vault = $this->findFile($user, $file);

        app(AuditLogService::class)->record('vault.downloaded', $vault, ['name' => $vault->original_name, 'area' => $this->area], (string) $user->company_id, $user->id);

        return $this->storage->download($vault);
    }

    public function preview(Request $request, string $file): StreamedResponse
    {
        $user = $request->user();
        $this->guardArea($user);

        return $this->storage->preview($this->findFile($user, $file));
    }

    public function destroy(Request $request, string $file): RedirectResponse
    {
        $user = $request->user();
        $this->guardArea($user);
        $vault = $this->findFile($user, $file);
        abort_unless($this->access->canDelete($user, $vault), 403);

        $vault->forceFill(['deleted_by' => $user->id])->save();
        $vault->delete(); // soft delete → Corbeille; the physical file is kept until purge.
        app(AuditLogService::class)->record('vault.deleted', $vault, ['name' => $vault->original_name, 'area' => $this->area], (string) $user->company_id, $user->id);

        return redirect()->route($this->routePrefix().'index')->with('status', __('vault_flash_deleted'));
    }

    protected function linksFileFor(Request $request, User $user): ?VaultFile
    {
        if ($this->area !== 'company' || ! $request->filled('links')) {
            return null;
        }
        $file = VaultFile::query()->withoutGlobalScopes()->where('company_id', $user->company_id)
            ->whereKey((string) $request->query('links'))->first();
        if (! $file || ! $this->access->canEditSharing($user, $file)) {
            return null;
        }
        $file->load(['shareLinks' => fn ($q) => $q->latest()->with('creator:id,name'), 'client:id,name,email,company_name']);

        return $file;
    }

    public function trash(Request $request): View
    {
        $user = $request->user();
        $this->guardArea($user);
        abort_unless($this->area === 'company' && $this->access->isManager($user), 403);

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'client_id' => (string) $request->query('client_id', ''),
            'project_id' => (string) $request->query('project_id', ''),
            'deleted_by' => (string) $request->query('deleted_by', ''),
            'from' => (string) $request->query('from', ''),
            'to' => (string) $request->query('to', ''),
        ];
        $q = VaultFile::query()->withoutGlobalScopes()->onlyTrashed()->where('company_id', $user->company_id)
            ->with(['project:id,title', 'client:id,name,company_name', 'uploader:id,name', 'deleter:id,name']);
        if ($filters['q'] !== '') {
            $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $filters['q']).'%';
            $q->where(fn ($w) => $w->where('original_name', 'like', $term)->orWhere('folder', 'like', $term)->orWhere('description', 'like', $term));
        }
        foreach (['client_id', 'project_id', 'deleted_by'] as $k) {
            if ($filters[$k] !== '') {
                $q->where($k, $filters[$k]);
            }
        }
        foreach (['from' => '>=', 'to' => '<='] as $key => $op) {
            if ($filters[$key] !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters[$key])) {
                $q->whereDate('deleted_at', $op, $filters[$key]);
            }
        }
        $files = $q->latest('deleted_at')->paginate(25)->withQueryString();
        $deleters = User::query()->whereIn('id', VaultFile::query()->withoutGlobalScopes()->onlyTrashed()
            ->where('company_id', $user->company_id)->whereNotNull('deleted_by')->distinct()->pluck('deleted_by'))
            ->orderBy('name')->get(['id', 'name']);

        return view('vault.trash', [
            'files' => $files,
            'filters' => $filters,
            'clients' => Client::query()->withoutGlobalScope('tenant')->where('company_id', $user->company_id)->orderBy('name')->get(['id', 'name', 'company_name']),
            'projects' => $this->access->uploadableProjects($user, 'company'),
            'deleters' => $deleters,
            'retentionDays' => (int) config('flowdesk.vault.trash_days', 30),
        ]);
    }

    public function restore(Request $request, string $file): RedirectResponse
    {
        $user = $request->user();
        $this->guardArea($user);
        abort_unless($this->area === 'company' && $this->access->isManager($user), 403);
        $vault = VaultFile::query()->withoutGlobalScopes()->onlyTrashed()->where('company_id', $user->company_id)->whereKey($file)->firstOrFail();
        $vault->restore();
        $vault->forceFill(['deleted_by' => null])->save();
        app(AuditLogService::class)->record('vault.restored', $vault, ['name' => $vault->original_name], (string) $user->company_id, $user->id);

        return redirect()->route('vault.trash')->with('status', __('vault_flash_restored'));
    }

    public function forceDestroy(Request $request, string $file): RedirectResponse
    {
        $user = $request->user();
        $this->guardArea($user);
        abort_unless($this->area === 'company' && $this->access->isManager($user), 403);
        $vault = VaultFile::query()->withoutGlobalScopes()->onlyTrashed()->where('company_id', $user->company_id)->whereKey($file)->firstOrFail();
        $name = $vault->original_name;
        app(\App\Services\Vault\VaultTrashService::class)->purge($vault);
        app(AuditLogService::class)->record('vault.force_deleted', null, ['name' => $name, 'file_id' => $file], (string) $user->company_id, $user->id);

        return redirect()->route('vault.trash')->with('status', __('vault_flash_force_deleted'));
    }

    protected function cleanFolder(?string $folder): ?string
    {
        $folder = trim(preg_replace('/\s+/', ' ', str_replace(['\\', '..'], ['/', ''], (string) $folder)) ?? '', " /");

        return $folder === '' ? null : mb_substr($folder, 0, 120);
    }

    protected function notifyClient(User $user, VaultFile $file, int $count): bool
    {
        $client = $file->client_id ? Client::query()->withoutGlobalScope('tenant')->where('company_id', $user->company_id)->find($file->client_id) : null;
        $company = Company::query()->find($user->company_id);
        $email = trim((string) ($client?->email ?: $client?->user?->email));
        if (! $client || ! $company || $email === '') {
            return false;
        }
        try {
            $url = flowdesk_tenant_url($company, route('portal.vault.index', [], false));
            app(TenantSmtpMailer::class)->send($company, new VaultFileSharedMail($file, $client, $company, $url), $email);
            $file->forceFill(['client_notified_at' => now()])->save();

            return true;
        } catch (Throwable $e) {
            Log::warning('vault.notify_failed', ['file' => $file->id, 'error' => $e->getMessage()]);

            return false;
        }
    }
}
