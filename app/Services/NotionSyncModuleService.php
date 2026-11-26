<?php

namespace App\Services;

use App\Models\InstalledModule;
use Illuminate\Support\Facades\Http;
class NotionSyncModuleService
{
    public const API_VERSION = '2022-06-28';

    /**
     * @return array{ok: bool, message: string}
     */
    public function testConnection(InstalledModule $module): array
    {
        $token = $this->token($module);
        if ($token === '') {
            return ['ok' => false, 'message' => __('Notion API token is not set.')];
        }

        $response = Http::withToken($token)
            ->withHeaders(['Notion-Version' => self::API_VERSION])
            ->get('https://api.notion.com/v1/users/me');

        if ($response->successful()) {
            $name = (string) data_get($response->json(), 'name', 'Notion');

            return ['ok' => true, 'message' => __('Connected as :name.', ['name' => $name])];
        }

        return ['ok' => false, 'message' => (string) ($response->json('message') ?? __('Notion connection failed.'))];
    }

    public function token(InstalledModule $module): string
    {
        $settings = $module->settings ?? [];

        return trim((string) ($settings['notion_api_token'] ?? ''));
    }

    public function databaseId(InstalledModule $module): string
    {
        $settings = $module->settings ?? [];

        return trim((string) ($settings['notion_database_id'] ?? ''));
    }

    public function syncEnabled(InstalledModule $module): bool
    {
        $settings = $module->settings ?? [];

        return (bool) ($settings['sync_enabled'] ?? false);
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function saveSettings(InstalledModule $module, array $credentials): void
    {
        $settings = $module->settings ?? [];
        if (array_key_exists('notion_api_token', $credentials)) {
            $token = trim((string) $credentials['notion_api_token']);
            if ($token !== '') {
                $settings['notion_api_token'] = $token;
            }
        }
        if (array_key_exists('notion_database_id', $credentials)) {
            $settings['notion_database_id'] = trim((string) $credentials['notion_database_id']);
        }
        if (array_key_exists('sync_enabled', $credentials)) {
            $settings['sync_enabled'] = (bool) $credentials['sync_enabled'];
        }

        $module->update(['settings' => $settings]);
    }
}
