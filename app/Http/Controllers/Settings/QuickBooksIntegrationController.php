<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Settings\Concerns\AuthorizesWorkspaceManagers;
use App\Http\Controllers\Settings\Concerns\RedirectsToIntegrationsHub;
use App\Models\CommerceIntegration;
use App\Models\Company;
use App\Services\Integrations\QuickBooksSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class QuickBooksIntegrationController extends Controller
{
    use AuthorizesWorkspaceManagers;
    use RedirectsToIntegrationsHub;

    public function __construct(private QuickBooksSyncService $quickbooks) {}

    public function edit(Request $request): RedirectResponse
    {
        $this->authorizeWorkspaceManagers($request);
        abort_if(! $request->user()->company instanceof Company, 403);

        return $this->integrationsHub('quickbooks');
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorizeWorkspaceManagers($request);
        $company = $request->user()->company;
        abort_if(! $company instanceof Company, 403);

        $data = $request->validate([
            'client_id' => ['required', 'string', 'max:255'],
            'client_secret' => ['nullable', 'string', 'max:512'],
            'realm_id' => ['required', 'string', 'max:64'],
            'refresh_token' => ['nullable', 'string', 'max:2048'],
            'access_token' => ['nullable', 'string', 'max:4096'],
            'environment' => ['required', 'in:production,sandbox'],
            'sync_clients' => ['nullable', 'boolean'],
            'sync_invoices' => ['nullable', 'boolean'],
            'sync_products' => ['nullable', 'boolean'],
            'sync_proposals' => ['nullable', 'boolean'],
        ]);

        $existing = CommerceIntegration::query()->where('provider', QuickBooksSyncService::PROVIDER)->first();
        $hasSecret = filled($data['client_secret'] ?? null) || filled($existing?->credentials['client_secret'] ?? null);
        $hasRefresh = filled($data['refresh_token'] ?? null) || filled($existing?->credentials['refresh_token'] ?? null);
        $hasAccess = filled($data['access_token'] ?? null) || filled($existing?->credentials['access_token'] ?? null);

        if (! $hasAccess && (! $hasRefresh || ! $hasSecret)) {
            return back()->withInput()->withErrors(['refresh_token' => __('quickbooks_token_required')]);
        }

        $this->quickbooks->upsertIntegration($company, $data, [
            'sync_clients' => $request->boolean('sync_clients'),
            'sync_invoices' => $request->boolean('sync_invoices'),
            'sync_products' => $request->boolean('sync_products'),
            'sync_proposals' => $request->boolean('sync_proposals'),
        ]);

        return $this->integrationsHub('quickbooks', __('quickbooks_settings_saved'));
    }

    public function test(Request $request): RedirectResponse
    {
        $this->authorizeWorkspaceManagers($request);
        $company = $request->user()->company;
        abort_if(! $company instanceof Company, 403);

        try {
            $label = $this->quickbooks->testConnection($company);

            return $this->integrationsHub('quickbooks', __('quickbooks_test_ok', ['company' => $label]));
        } catch (Throwable $e) {
            Log::warning('quickbooks.test_failed', ['company_id' => $company->id, 'message' => $e->getMessage()]);

            return $this->integrationsHub('quickbooks')->withErrors(['quickbooks' => $e->getMessage()]);
        }
    }

    public function sync(Request $request): RedirectResponse
    {
        $this->authorizeWorkspaceManagers($request);
        $company = $request->user()->company;
        abort_if(! $company instanceof Company, 403);

        if (! $this->quickbooks->isConfigured($company)) {
            return $this->integrationsHub('quickbooks')->withErrors(['quickbooks' => __('quickbooks_not_configured')]);
        }

        try {
            $result = $this->quickbooks->pull($company, $request->user());

            return $this->integrationsHub('quickbooks', __('quickbooks_sync_summary', [
                'clients' => ($result['clients']['created'] ?? 0) + ($result['clients']['updated'] ?? 0),
                'invoices' => $result['invoices']['created'] ?? 0,
                'products' => ($result['products']['created'] ?? 0) + ($result['products']['updated'] ?? 0),
                'proposals' => $result['proposals']['created'] ?? 0,
            ]));
        } catch (Throwable $e) {
            Log::warning('quickbooks.sync_failed', ['company_id' => $company->id, 'message' => $e->getMessage()]);

            return $this->integrationsHub('quickbooks')->withErrors(['quickbooks' => $e->getMessage()]);
        }
    }

    public function disconnect(Request $request): RedirectResponse
    {
        $this->authorizeWorkspaceManagers($request);
        $company = $request->user()->company;
        abort_if(! $company instanceof Company, 403);

        $this->quickbooks->disconnect($company);

        return $this->integrationsHub('quickbooks', __('quickbooks_disconnected'));
    }
}
