<?php

namespace App\Services\Integrations;

use App\Enums\ProjectSource;
use App\Enums\ProjectStatus;
use App\Models\CommerceIntegration;
use App\Models\CommerceSyncLog;
use App\Models\Company;
use App\Models\Project;
use App\Models\User;
use App\Services\NotionSyncModuleService;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class NotionIntegrationService
{
    public const PROVIDER = 'notion';

    /**
     * @param  array<string, mixed>  $credentials
     * @param  array<string, mixed>  $settings
     */
    public function upsertIntegration(Company $company, array $credentials, array $settings = []): CommerceIntegration
    {
        $existing = $this->integration($company);
        $current = $existing?->credentials ?? [];

        $token = trim((string) ($credentials['api_token'] ?? ''));
        if ($token === '' && is_string($current['api_token'] ?? null)) {
            $token = (string) $current['api_token'];
        }

        return CommerceIntegration::query()->withoutGlobalScopes()->updateOrCreate(
            ['company_id' => $company->id, 'provider' => self::PROVIDER],
            [
                'credentials' => [
                    'api_token' => $token,
                    'database_id' => trim((string) ($credentials['database_id'] ?? $current['database_id'] ?? '')),
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

        return trim((string) ($creds['api_token'] ?? '')) !== ''
            && trim((string) ($creds['database_id'] ?? '')) !== '';
    }

    public function isActive(Company $company): bool
    {
        $settings = $this->integration($company)?->settings ?? [];

        return $this->isConfigured($company) && (bool) ($settings['sync_enabled'] ?? false);
    }

    public function testConnection(Company $company): string
    {
        $response = $this->client($company)->get('https://api.notion.com/v1/users/me');
        if (! $response->successful()) {
            throw new RuntimeException((string) ($response->json('message') ?? __('notion_connection_failed')));
        }

        $name = trim((string) data_get($response->json(), 'name', 'Notion'));

        return $name !== '' ? $name : 'Notion';
    }

    /**
     * @return array{pulled: int, pushed: int}
     */
    public function sync(Company $company, User $user): array
    {
        if (! $this->isConfigured($company)) {
            $this->log($company, 'sync', 'skipped', __('notion_not_configured'));

            throw new RuntimeException(__('notion_not_configured'));
        }

        $pulled = $this->pullPages($company, $user);
        $pushed = $this->pushLocalProjects($company);

        $this->integration($company)?->update(['last_synced_at' => now()]);
        $this->log($company, 'sync', 'ok', __('notion_sync_summary', [
            'pulled' => $pulled,
            'pushed' => $pushed,
        ]), compact('pulled', 'pushed'));

        return ['pulled' => $pulled, 'pushed' => $pushed];
    }

    /**
     * @return array{ok: bool, message: string, page_id: string|null}
     */
    public function pushProject(Project $project): array
    {
        $company = $project->company ?? Company::query()->find($project->company_id);
        if (! $company instanceof Company || ! $this->isConfigured($company)) {
            throw new RuntimeException(__('notion_not_configured'));
        }

        $pageId = trim((string) ($project->notion_page_id ?? ''));
        $properties = $this->pageProperties($project);

        if ($pageId !== '') {
            $response = $this->client($company)->patch('https://api.notion.com/v1/pages/'.$pageId, [
                'properties' => $properties,
            ]);
            if (! $response->successful()) {
                throw new RuntimeException((string) ($response->json('message') ?? __('notion_sync_failed')));
            }

            return [
                'ok' => true,
                'message' => __('notion_project_updated'),
                'page_id' => $pageId,
            ];
        }

        $databaseId = (string) ($this->integration($company)?->credentials['database_id'] ?? '');
        $response = $this->client($company)->post('https://api.notion.com/v1/pages', [
            'parent' => ['database_id' => $databaseId],
            'properties' => $properties,
        ]);

        if (! $response->successful()) {
            $response = $this->client($company)->post('https://api.notion.com/v1/pages', [
                'parent' => ['database_id' => $databaseId],
                'properties' => [
                    'Name' => $properties['Name'],
                ],
            ]);
        }

        if (! $response->successful()) {
            throw new RuntimeException((string) ($response->json('message') ?? __('notion_sync_failed')));
        }

        $pageId = (string) ($response->json('id') ?? '');
        if ($pageId !== '') {
            $project->forceFill(['notion_page_id' => $pageId])->save();
        }

        return [
            'ok' => true,
            'message' => __('notion_project_created'),
            'page_id' => $pageId !== '' ? $pageId : null,
        ];
    }

    private function pullPages(Company $company, User $user): int
    {
        $databaseId = (string) ($this->integration($company)?->credentials['database_id'] ?? '');
        $response = $this->client($company)->post('https://api.notion.com/v1/databases/'.$databaseId.'/query', [
            'page_size' => 50,
        ]);
        if (! $response->successful()) {
            throw new RuntimeException((string) ($response->json('message') ?? __('notion_connection_failed')));
        }

        $count = 0;
        foreach ($response->json('results') ?? [] as $page) {
            if (! is_array($page)) {
                continue;
            }
            $pageId = trim((string) ($page['id'] ?? ''));
            $title = $this->titleFromPage($page);
            if ($pageId === '' || $title === '') {
                continue;
            }

            $project = Project::query()->withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->where('notion_page_id', $pageId)
                ->first();

            if (! $project) {
                Project::query()->withoutGlobalScopes()->create([
                    'company_id' => $company->id,
                    'created_by' => $user->id,
                    'title' => mb_substr($title, 0, 255),
                    'status' => ProjectStatus::InProgress,
                    'source' => ProjectSource::Internal,
                    'notion_page_id' => $pageId,
                ]);
            } else {
                $project->update(['title' => mb_substr($title, 0, 255)]);
            }
            $count++;
        }

        return $count;
    }

    private function pushLocalProjects(Company $company): int
    {
        $projects = Project::query()->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where(function ($q): void {
                $q->whereNull('notion_page_id')->orWhere('notion_page_id', '');
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
     * @return array<string, mixed>
     */
    private function pageProperties(Project $project): array
    {
        $status = $project->status instanceof ProjectStatus ? $project->status->value : (string) $project->status;

        return [
            'Name' => [
                'title' => [
                    ['text' => ['content' => mb_substr((string) $project->title, 0, 2000)]],
                ],
            ],
            'Status' => [
                'rich_text' => [
                    ['text' => ['content' => mb_substr($status, 0, 2000)]],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $page
     */
    private function titleFromPage(array $page): string
    {
        $properties = is_array($page['properties'] ?? null) ? $page['properties'] : [];
        foreach ($properties as $property) {
            if (! is_array($property) || ($property['type'] ?? '') !== 'title') {
                continue;
            }
            $chunks = $property['title'] ?? [];
            if (! is_array($chunks)) {
                continue;
            }
            $text = collect($chunks)->map(fn ($chunk) => is_array($chunk) ? (string) ($chunk['plain_text'] ?? '') : '')->implode('');
            if (trim($text) !== '') {
                return trim($text);
            }
        }

        return '';
    }

    private function client(Company $company): PendingRequest
    {
        $token = (string) ($this->integration($company)?->credentials['api_token'] ?? '');

        return Http::withToken($token)
            ->withHeaders(['Notion-Version' => NotionSyncModuleService::API_VERSION])
            ->acceptJson()
            ->timeout(30);
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
