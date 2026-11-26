<?php

namespace App\Services\Odoo;

use App\Models\Company;
use App\Models\CompanySetting;
use App\Services\PlanLimitService;
use Illuminate\Support\Facades\Crypt;
use Throwable;

class OdooConfigService
{
    /**
     * @return array{
     *   enabled: bool,
     *   url: string|null,
     *   database: string|null,
     *   username: string|null,
     *   api_key: string|null,
     *   auto_push: bool,
     *   sync_clients: bool,
     *   sync_projects: bool,
     *   sync_invoices: bool,
     *   sync_proposals: bool,
     *   last_sync_at: string|null,
     *   last_sync_status: string|null,
     *   last_sync_message: string|null,
     *   uid: int|null
     * }
     */
    public function getResolved(Company $company): array
    {
        $raw = $this->raw($company);
        $odoo = is_array($raw['odoo'] ?? null) ? $raw['odoo'] : [];

        return [
            'enabled' => (bool) ($odoo['enabled'] ?? false),
            'url' => $this->nullableString($odoo['url'] ?? null),
            'database' => $this->nullableString($odoo['database'] ?? null),
            'username' => $this->nullableString($odoo['username'] ?? null),
            'api_key' => $this->decryptString($odoo['api_key_enc'] ?? null),
            'auto_push' => (bool) ($odoo['auto_push'] ?? true),
            'sync_clients' => (bool) ($odoo['sync_clients'] ?? true),
            'sync_projects' => (bool) ($odoo['sync_projects'] ?? true),
            'sync_invoices' => (bool) ($odoo['sync_invoices'] ?? true),
            'sync_proposals' => (bool) ($odoo['sync_proposals'] ?? true),
            'last_sync_at' => $this->nullableString($odoo['last_sync_at'] ?? null),
            'last_sync_status' => $this->nullableString($odoo['last_sync_status'] ?? null),
            'last_sync_message' => $this->nullableString($odoo['last_sync_message'] ?? null),
            'uid' => isset($odoo['uid']) && is_numeric($odoo['uid']) ? (int) $odoo['uid'] : null,
        ];
    }

    /**
     * @return array{
     *   enabled: bool,
     *   url: string,
     *   database: string,
     *   username: string,
     *   has_api_key: bool,
     *   auto_push: bool,
     *   sync_clients: bool,
     *   sync_projects: bool,
     *   sync_invoices: bool,
     *   sync_proposals: bool,
     *   last_sync_at: string|null,
     *   last_sync_status: string|null,
     *   last_sync_message: string|null,
     *   connected: bool
     * }
     */
    public function toFormArray(Company $company): array
    {
        $c = $this->getResolved($company);
        $hasKey = is_string($c['api_key']) && $c['api_key'] !== '';

        return [
            'enabled' => $c['enabled'],
            'url' => (string) ($c['url'] ?? ''),
            'database' => (string) ($c['database'] ?? ''),
            'username' => (string) ($c['username'] ?? ''),
            'has_api_key' => $hasKey,
            'auto_push' => $c['auto_push'],
            'sync_clients' => $c['sync_clients'],
            'sync_projects' => $c['sync_projects'],
            'sync_invoices' => $c['sync_invoices'],
            'sync_proposals' => $c['sync_proposals'],
            'last_sync_at' => $c['last_sync_at'],
            'last_sync_status' => $c['last_sync_status'],
            'last_sync_message' => $c['last_sync_message'],
            'connected' => $this->isConfigured($company),
        ];
    }

    public function isConfigured(Company $company): bool
    {
        if (! app(PlanLimitService::class)->isFeatureEnabled($company, 'odoo')) {
            return false;
        }

        $c = $this->getResolved($company);

        return $c['enabled']
            && is_string($c['url']) && $c['url'] !== ''
            && is_string($c['database']) && $c['database'] !== ''
            && is_string($c['username']) && $c['username'] !== ''
            && is_string($c['api_key']) && $c['api_key'] !== '';
    }

    public function rememberUid(Company $company, int $uid): void
    {
        $settings = $this->ensureSettings($company);
        $channels = is_array($settings->integration_channels) ? $settings->integration_channels : [];
        $odoo = is_array($channels['odoo'] ?? null) ? $channels['odoo'] : [];
        $odoo['uid'] = $uid;
        $channels['odoo'] = $odoo;
        $settings->integration_channels = $channels;
        $settings->save();
        $company->setRelation('settings', $settings);
    }

    public function rememberSyncResult(Company $company, string $status, string $message): void
    {
        $settings = $this->ensureSettings($company);
        $channels = is_array($settings->integration_channels) ? $settings->integration_channels : [];
        $odoo = is_array($channels['odoo'] ?? null) ? $channels['odoo'] : [];
        $odoo['last_sync_at'] = now()->toIso8601String();
        $odoo['last_sync_status'] = $status;
        $odoo['last_sync_message'] = mb_substr($message, 0, 2000);
        $channels['odoo'] = $odoo;
        $settings->integration_channels = $channels;
        $settings->save();
        $company->setRelation('settings', $settings);
    }

    public function disconnect(Company $company): void
    {
        $settings = $this->ensureSettings($company);
        $channels = is_array($settings->integration_channels) ? $settings->integration_channels : [];
        unset($channels['odoo']);
        $settings->integration_channels = $channels;
        $settings->save();
        $company->setRelation('settings', $settings);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveFromRequest(Company $company, array $data): void
    {
        $settings = $this->ensureSettings($company);
        $channels = is_array($settings->integration_channels) ? $settings->integration_channels : [];
        $odoo = is_array($channels['odoo'] ?? null) ? $channels['odoo'] : [];

        $odoo['enabled'] = (bool) ($data['enabled'] ?? false);
        $odoo['url'] = isset($data['url']) ? rtrim(trim((string) $data['url']), '/') : ($odoo['url'] ?? null);
        $odoo['database'] = isset($data['database']) ? trim((string) $data['database']) : ($odoo['database'] ?? null);
        $odoo['username'] = isset($data['username']) ? trim((string) $data['username']) : ($odoo['username'] ?? null);
        $odoo['auto_push'] = (bool) ($data['auto_push'] ?? false);
        $odoo['sync_clients'] = (bool) ($data['sync_clients'] ?? false);
        $odoo['sync_projects'] = (bool) ($data['sync_projects'] ?? false);
        $odoo['sync_invoices'] = (bool) ($data['sync_invoices'] ?? false);
        $odoo['sync_proposals'] = (bool) ($data['sync_proposals'] ?? false);

        $apiKey = $data['api_key'] ?? null;
        if (is_string($apiKey) && $apiKey !== '' && $apiKey !== '********') {
            $odoo['api_key_enc'] = Crypt::encryptString($apiKey);
            unset($odoo['uid']);
        } elseif (! empty($data['clear_api_key'])) {
            $odoo['api_key_enc'] = null;
            unset($odoo['uid']);
        }

        $channels['odoo'] = $odoo;
        $settings->integration_channels = $channels;
        $settings->save();
        $company->setRelation('settings', $settings);
    }

    private function ensureSettings(Company $company): CompanySetting
    {
        return $company->settings()->firstOrCreate([]);
    }

    /**
     * @return array<string, mixed>
     */
    private function raw(Company $company): array
    {
        $row = $company->settings;
        $raw = is_array($row?->integration_channels) ? $row->integration_channels : [];

        return is_array($raw) ? $raw : [];
    }

    private function decryptString(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }
        try {
            return Crypt::decryptString($value);
        } catch (Throwable) {
            return null;
        }
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
