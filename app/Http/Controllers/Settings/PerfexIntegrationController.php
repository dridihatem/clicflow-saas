<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Settings\Concerns\AuthorizesWorkspaceManagers;
use App\Http\Controllers\Settings\Concerns\RedirectsToIntegrationsHub;
use App\Models\CommerceIntegration;
use App\Models\Company;
use App\Services\Integrations\PerfexSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class PerfexIntegrationController extends Controller
{
    use AuthorizesWorkspaceManagers;
    use RedirectsToIntegrationsHub;

    public function __construct(private PerfexSyncService $perfex) {}

    public function edit(Request $request): RedirectResponse
    {
        $this->authorizeWorkspaceManagers($request);
        abort_if(! $request->user()->company instanceof Company, 403);

        return $this->integrationsHub('perfex');
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorizeWorkspaceManagers($request);
        $company = $request->user()->company;
        abort_if(! $company instanceof Company, 403);

        $data = $request->validate([
            'base_url' => ['required', 'url', 'max:255'],
            'api_token' => ['nullable', 'string', 'max:512'],
            'sync_prospects' => ['nullable', 'boolean'],
            'sync_clients' => ['nullable', 'boolean'],
            'sync_invoices' => ['nullable', 'boolean'],
            'sync_products' => ['nullable', 'boolean'],
        ]);

        $existing = CommerceIntegration::query()->where('provider', PerfexSyncService::PROVIDER)->first();
        if (blank($data['api_token'] ?? null) && blank($existing?->credentials['api_token'] ?? null)) {
            return back()->withInput()->withErrors(['api_token' => __('perfex_api_token_required')]);
        }

        $this->perfex->upsertIntegration($company, $data, [
            'sync_prospects' => $request->boolean('sync_prospects'),
            'sync_clients' => $request->boolean('sync_clients'),
            'sync_invoices' => $request->boolean('sync_invoices'),
            'sync_products' => $request->boolean('sync_products'),
        ]);

        return $this->integrationsHub('perfex', __('perfex_settings_saved'));
    }

    public function test(Request $request): RedirectResponse
    {
        $this->authorizeWorkspaceManagers($request);
        $company = $request->user()->company;
        abort_if(! $company instanceof Company, 403);

        try {
            $label = $this->perfex->testConnection($company);

            return $this->integrationsHub('perfex', __('perfex_test_ok', ['version' => $label]));
        } catch (Throwable $e) {
            Log::warning('perfex.test_failed', ['company_id' => $company->id, 'message' => $e->getMessage()]);

            return $this->integrationsHub('perfex')->withErrors(['perfex' => $e->getMessage()]);
        }
    }

    public function sync(Request $request): RedirectResponse
    {
        $this->authorizeWorkspaceManagers($request);
        $company = $request->user()->company;
        abort_if(! $company instanceof Company, 403);

        if (! $this->perfex->isConfigured($company)) {
            return $this->integrationsHub('perfex')->withErrors(['perfex' => __('perfex_not_configured')]);
        }

        try {
            $result = $this->perfex->pull($company, $request->user());

            return $this->integrationsHub('perfex', __('perfex_sync_summary', [
                'clients' => ($result['clients']['created'] ?? 0) + ($result['clients']['updated'] ?? 0),
                'prospects' => ($result['prospects']['created'] ?? 0) + ($result['prospects']['updated'] ?? 0),
                'invoices' => $result['invoices']['created'] ?? 0,
                'products' => ($result['products']['created'] ?? 0) + ($result['products']['updated'] ?? 0),
            ]));
        } catch (Throwable $e) {
            Log::warning('perfex.sync_failed', ['company_id' => $company->id, 'message' => $e->getMessage()]);

            return $this->integrationsHub('perfex')->withErrors(['perfex' => $e->getMessage()]);
        }
    }

    public function disconnect(Request $request): RedirectResponse
    {
        $this->authorizeWorkspaceManagers($request);
        $company = $request->user()->company;
        abort_if(! $company instanceof Company, 403);

        $this->perfex->disconnect($company);

        return $this->integrationsHub('perfex', __('perfex_disconnected'));
    }
}
