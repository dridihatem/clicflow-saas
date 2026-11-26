<?php

namespace App\Services\Integrations;

use App\Enums\ProjectSource;
use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Models\CommerceIntegration;
use App\Models\CommerceSyncLog;
use App\Models\Company;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\User;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class TrelloProjectSyncService
{
    public const PROVIDER = 'trello';

    /**
     * @param  array<string, mixed>  $credentials
     * @param  array<string, mixed>  $settings
     */
    public function upsertIntegration(Company $company, array $credentials, array $settings = []): CommerceIntegration
    {
        $existing = $this->integration($company);
        $current = $existing?->credentials ?? [];

        $key = trim((string) ($credentials['api_key'] ?? ''));
        if ($key === '' && is_string($current['api_key'] ?? null)) {
            $key = (string) $current['api_key'];
        }

        $token = trim((string) ($credentials['api_token'] ?? ''));
        if ($token === '' && is_string($current['api_token'] ?? null)) {
            $token = (string) $current['api_token'];
        }

        return CommerceIntegration::query()->withoutGlobalScopes()->updateOrCreate(
            ['company_id' => $company->id, 'provider' => self::PROVIDER],
            [
                'credentials' => [
                    'api_key' => $key,
                    'api_token' => $token,
                ],
                'settings' => [
                    'sync_enabled' => (bool) ($settings['sync_enabled'] ?? true),
                ],
                'auto_push' => false,
            ],
        );
    }

    public function disconnect(Company $company): void
    {
        $this->integration($company)?->delete();
    }

    public function isConfigured(Company $company): bool
    {
        $creds = $this->integration($company)?->credentials ?? [];

        return trim((string) ($creds['api_key'] ?? '')) !== ''
            && trim((string) ($creds['api_token'] ?? '')) !== '';
    }

    public function isActive(Company $company): bool
    {
        $settings = $this->integration($company)?->settings ?? [];

        return $this->isConfigured($company) && (bool) ($settings['sync_enabled'] ?? false);
    }

    public function testConnection(Company $company): string
    {
        $response = $this->get($company, 'members/me', ['fields' => 'fullName,username']);
        if (! $response->successful()) {
            throw new RuntimeException(__('trello_connection_failed'));
        }

        $name = trim((string) ($response->json('fullName') ?: $response->json('username') ?: 'Trello'));

        return $name !== '' ? $name : 'Trello';
    }

    /**
     * @return array{pulled: int, pushed: int}
     */
    public function sync(Company $company, User $user): array
    {
        if (! $this->isConfigured($company)) {
            $this->log($company, 'sync', 'skipped', __('trello_not_configured'));

            throw new RuntimeException(__('trello_not_configured'));
        }

        $pulled = $this->pullBoards($company, $user);
        $pushed = $this->pushLocalProjects($company);

        $this->integration($company)?->update(['last_synced_at' => now()]);
        $this->log($company, 'sync', 'ok', __('trello_sync_summary', [
            'pulled' => $pulled,
            'pushed' => $pushed,
        ]), compact('pulled', 'pushed'));

        return ['pulled' => $pulled, 'pushed' => $pushed];
    }

    /**
     * @return array{ok: bool, message: string, board_id: string|null}
     */
    public function pushProject(Project $project): array
    {
        $company = $project->company ?? Company::query()->find($project->company_id);
        if (! $company instanceof Company || ! $this->isConfigured($company)) {
            throw new RuntimeException(__('trello_not_configured'));
        }

        $project->loadMissing('tasks');
        $boardId = trim((string) ($project->trello_board_id ?? ''));
        $payload = [
            'name' => mb_substr((string) $project->title, 0, 16384),
            'desc' => mb_substr((string) ($project->description ?? ''), 0, 16384),
        ];

        if ($boardId !== '') {
            $response = $this->put($company, 'boards/'.$boardId, $payload);
            if (! $response->successful()) {
                throw new RuntimeException((string) ($response->json('message') ?? __('trello_sync_failed')));
            }

            return [
                'ok' => true,
                'message' => __('trello_project_updated'),
                'board_id' => $boardId,
            ];
        }

        $created = $this->post($company, 'boards', $payload + ['defaultLists' => 'false']);
        if (! $created->successful()) {
            throw new RuntimeException((string) ($created->json('message') ?? __('trello_sync_failed')));
        }

        $boardId = (string) ($created->json('id') ?? '');
        if ($boardId === '') {
            throw new RuntimeException(__('trello_sync_failed'));
        }

        $project->forceFill(['trello_board_id' => $boardId])->save();
        $lists = $this->ensureLists($company, $boardId);
        $this->pushTasks($company, $project, $lists);

        return [
            'ok' => true,
            'message' => __('trello_project_created'),
            'board_id' => $boardId,
        ];
    }

    private function pullBoards(Company $company, User $user): int
    {
        $response = $this->get($company, 'members/me/boards', [
            'filter' => 'open',
            'fields' => 'id,name,desc,closed',
        ]);
        if (! $response->successful()) {
            throw new RuntimeException(__('trello_connection_failed'));
        }

        $count = 0;
        foreach ($response->json() ?? [] as $board) {
            if (! is_array($board)) {
                continue;
            }
            $boardId = trim((string) ($board['id'] ?? ''));
            $name = trim((string) ($board['name'] ?? ''));
            if ($boardId === '' || $name === '') {
                continue;
            }

            $project = Project::query()->withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->where('trello_board_id', $boardId)
                ->first();

            if (! $project) {
                $project = Project::query()->withoutGlobalScopes()->create([
                    'company_id' => $company->id,
                    'created_by' => $user->id,
                    'title' => mb_substr($name, 0, 255),
                    'description' => $board['desc'] ?? null,
                    'status' => ProjectStatus::InProgress,
                    'source' => ProjectSource::Internal,
                    'trello_board_id' => $boardId,
                ]);
            } else {
                $project->update([
                    'title' => mb_substr($name, 0, 255),
                    'description' => $board['desc'] ?? $project->description,
                ]);
            }

            $this->pullCards($company, $project, $boardId);
            $count++;
        }

        return $count;
    }

    private function pullCards(Company $company, Project $project, string $boardId): void
    {
        $response = $this->get($company, 'boards/'.$boardId.'/cards', [
            'fields' => 'id,name,desc,closed',
            'filter' => 'open',
        ]);
        if (! $response->successful()) {
            return;
        }

        $existing = ProjectTask::query()->withoutGlobalScopes()
            ->where('project_id', $project->id)
            ->pluck('title')
            ->map(fn ($title) => mb_strtolower(trim((string) $title)))
            ->all();

        $sort = (int) ProjectTask::query()->withoutGlobalScopes()->where('project_id', $project->id)->max('sort_order');
        foreach ($response->json() ?? [] as $card) {
            if (! is_array($card)) {
                continue;
            }
            $title = trim((string) ($card['name'] ?? ''));
            if ($title === '' || in_array(mb_strtolower($title), $existing, true)) {
                continue;
            }

            $sort++;
            ProjectTask::query()->withoutGlobalScopes()->create([
                'company_id' => $company->id,
                'project_id' => $project->id,
                'title' => mb_substr($title, 0, 255),
                'description' => $card['desc'] ?? null,
                'status' => TaskStatus::Todo,
                'sort_order' => $sort,
            ]);
            $existing[] = mb_strtolower($title);
        }
    }

    private function pushLocalProjects(Company $company): int
    {
        $projects = Project::query()->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where(function ($q): void {
                $q->whereNull('trello_board_id')->orWhere('trello_board_id', '');
            })
            ->limit(40)
            ->get();

        $count = 0;
        foreach ($projects as $project) {
            $this->pushProject($project);
            $count++;
        }

        return $count;
    }

    /**
     * @return array<string, string>
     */
    private function ensureLists(Company $company, string $boardId): array
    {
        $names = [
            TaskStatus::Todo->value => 'To Do',
            TaskStatus::InProgress->value => 'In Progress',
            TaskStatus::Review->value => 'Review',
            TaskStatus::Done->value => 'Done',
        ];

        $lists = [];
        foreach ($names as $status => $name) {
            $response = $this->post($company, 'lists', [
                'name' => $name,
                'idBoard' => $boardId,
            ]);
            if ($response->successful()) {
                $lists[$status] = (string) $response->json('id');
            }
        }

        return $lists;
    }

    /**
     * @param  array<string, string>  $lists
     */
    private function pushTasks(Company $company, Project $project, array $lists): void
    {
        $fallback = $lists[TaskStatus::Todo->value] ?? reset($lists);
        if (! is_string($fallback) || $fallback === '') {
            return;
        }

        foreach ($project->tasks as $task) {
            $status = $task->status instanceof TaskStatus ? $task->status->value : (string) $task->status;
            $listId = $lists[$status] ?? $fallback;
            $this->post($company, 'cards', [
                'idList' => $listId,
                'name' => mb_substr((string) $task->title, 0, 16384),
                'desc' => mb_substr((string) ($task->description ?? ''), 0, 16384),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function get(Company $company, string $path, array $query = []): Response
    {
        return $this->request($company, 'get', $path, $query);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function post(Company $company, string $path, array $payload = []): Response
    {
        return $this->request($company, 'post', $path, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function put(Company $company, string $path, array $payload = []): Response
    {
        return $this->request($company, 'put', $path, $payload);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function request(Company $company, string $method, string $path, array $data = []): Response
    {
        $creds = $this->integration($company)?->credentials ?? [];
        $auth = [
            'key' => (string) ($creds['api_key'] ?? ''),
            'token' => (string) ($creds['api_token'] ?? ''),
        ];

        $http = Http::acceptJson()->timeout(30);
        $url = 'https://api.trello.com/1/'.ltrim($path, '/');

        return match ($method) {
            'post' => $http->asForm()->post($url, $data + $auth),
            'put' => $http->asForm()->put($url, $data + $auth),
            default => $http->get($url, $data + $auth),
        };
    }

    private function integration(Company $company): ?CommerceIntegration
    {
        return CommerceIntegration::query()
            ->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('provider', self::PROVIDER)
            ->first();
    }

    private function log(Company $company, string $direction, string $status, string $summary, mixed $payload = null): void
    {
        CommerceSyncLog::query()->withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'provider' => self::PROVIDER,
            'direction' => $direction,
            'status' => $status,
            'summary' => $summary,
            'payload' => is_array($payload) ? $payload : null,
        ]);
    }
}
