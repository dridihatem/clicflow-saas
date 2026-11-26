<?php

namespace App\Services;

use App\Models\InstalledModule;
use App\Models\Project;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class NotionProjectSyncService
{
    public function __construct(
        private NotionSyncModuleService $notionSettings,
    ) {}

    /**
     * @return array{ok: bool, message: string, page_id: string|null}
     */
    public function syncProject(Project $project, InstalledModule $module): array
    {
        $token = $this->notionSettings->token($module);
        $databaseId = $this->notionSettings->databaseId($module);

        if ($token === '') {
            throw new RuntimeException(__('Notion API token is not set.'));
        }
        if ($databaseId === '') {
            throw new RuntimeException(__('Notion database ID is not set.'));
        }
        if (! $this->notionSettings->syncEnabled($module)) {
            throw new RuntimeException(__('Notion sync is disabled in module settings.'));
        }

        $payload = [
            'parent' => ['database_id' => $databaseId],
            'properties' => [
                'Name' => [
                    'title' => [
                        ['text' => ['content' => mb_substr((string) $project->title, 0, 2000)]],
                    ],
                ],
            ],
        ];

        $status = $project->status instanceof \BackedEnum ? $project->status->value : (string) $project->status;
        if ($status !== '') {
            $payload['properties']['Status'] = [
                'rich_text' => [
                    ['text' => ['content' => mb_substr($status, 0, 2000)]],
                ],
            ];
        }

        $response = Http::withToken($token)
            ->withHeaders(['Notion-Version' => NotionSyncModuleService::API_VERSION])
            ->post('https://api.notion.com/v1/pages', $payload);

        if (! $response->successful()) {
            $message = (string) ($response->json('message') ?? __('Notion sync failed.'));

            return ['ok' => false, 'message' => $message, 'page_id' => null];
        }

        return [
            'ok' => true,
            'message' => __('Project synced to Notion.'),
            'page_id' => (string) ($response->json('id') ?? ''),
        ];
    }
}
