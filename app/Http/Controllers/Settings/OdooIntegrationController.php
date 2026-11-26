<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Settings\Concerns\AuthorizesWorkspaceManagers;
use App\Http\Controllers\Settings\Concerns\RedirectsToIntegrationsHub;
use App\Jobs\SyncCompanyWithOdooJob;
use App\Models\Company;
use App\Services\Odoo\OdooConfigService;
use App\Services\Odoo\OdooJsonRpcClient;
use App\Services\Odoo\OdooSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class OdooIntegrationController extends Controller
{
    use AuthorizesWorkspaceManagers;
    use RedirectsToIntegrationsHub;

    public function __construct(
        private OdooConfigService $config,
        private OdooJsonRpcClient $client,
        private OdooSyncService $sync,
    ) {}

    public function edit(Request $request): RedirectResponse
    {
        $this->authorizeWorkspaceManagers($request);
        abort_if(! $request->user()->company instanceof Company, 403);

        return $this->integrationsHub('odoo');
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorizeWorkspaceManagers($request);
        $company = $request->user()->company;
        abort_if(! $company instanceof Company, 403);

        $data = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'url' => ['nullable', 'url', 'max:255'],
            'database' => ['nullable', 'string', 'max:128'],
            'username' => ['nullable', 'string', 'max:255'],
            'api_key' => ['nullable', 'string', 'max:512'],
            'clear_api_key' => ['nullable', 'boolean'],
            'auto_push' => ['nullable', 'boolean'],
            'sync_clients' => ['nullable', 'boolean'],
            'sync_projects' => ['nullable', 'boolean'],
            'sync_invoices' => ['nullable', 'boolean'],
            'sync_proposals' => ['nullable', 'boolean'],
        ]);

        $data['enabled'] = $request->boolean('enabled');
        $data['clear_api_key'] = $request->boolean('clear_api_key');
        $data['auto_push'] = $request->boolean('auto_push');
        $data['sync_clients'] = $request->boolean('sync_clients');
        $data['sync_projects'] = $request->boolean('sync_projects');
        $data['sync_invoices'] = $request->boolean('sync_invoices');
        $data['sync_proposals'] = $request->boolean('sync_proposals');

        if ($data['enabled']) {
            $request->validate([
                'url' => ['required', 'url', 'max:255'],
                'database' => ['required', 'string', 'max:128'],
                'username' => ['required', 'string', 'max:255'],
            ]);
            if (! $this->config->toFormArray($company)['has_api_key'] && blank($data['api_key'] ?? null)) {
                return back()->withInput()->withErrors(['api_key' => __('odoo_api_key_required')]);
            }
        }

        $this->config->saveFromRequest($company, $data);

        return $this->integrationsHub('odoo', __('odoo_settings_saved'));
    }

    public function test(Request $request): RedirectResponse
    {
        $this->authorizeWorkspaceManagers($request);
        $company = $request->user()->company;
        abort_if(! $company instanceof Company, 403);

        try {
            $version = $this->client->testConnection($company);

            return $this->integrationsHub('odoo', __('odoo_test_ok', ['version' => $version]));
        } catch (Throwable $e) {
            Log::warning('odoo.test_failed', ['company_id' => $company->id, 'message' => $e->getMessage()]);

            return $this->integrationsHub('odoo')->withErrors(['odoo' => $e->getMessage()]);
        }
    }

    public function sync(Request $request): RedirectResponse
    {
        $this->authorizeWorkspaceManagers($request);
        $company = $request->user()->company;
        abort_if(! $company instanceof Company, 403);

        if (! $this->config->isConfigured($company)) {
            return $this->integrationsHub('odoo')->withErrors(['odoo' => __('odoo_not_configured')]);
        }

        $runNow = $request->boolean('now');

        if ($runNow) {
            try {
                $result = $this->sync->syncCompany($company);

                return $this->integrationsHub('odoo', __('odoo_sync_summary', [
                    'pushed' => array_sum($result['pushed']),
                    'pulled' => array_sum($result['pulled']),
                    'errors' => count($result['errors']),
                ]));
            } catch (Throwable $e) {
                Log::warning('odoo.sync_failed', ['company_id' => $company->id, 'message' => $e->getMessage()]);

                return $this->integrationsHub('odoo')->withErrors(['odoo' => $e->getMessage()]);
            }
        }

        SyncCompanyWithOdooJob::dispatch((string) $company->id);

        return $this->integrationsHub('odoo', __('odoo_sync_queued'));
    }

    public function disconnect(Request $request): RedirectResponse
    {
        $this->authorizeWorkspaceManagers($request);
        $company = $request->user()->company;
        abort_if(! $company instanceof Company, 403);

        $this->config->disconnect($company);

        return $this->integrationsHub('odoo', __('odoo_disconnected'));
    }
}
