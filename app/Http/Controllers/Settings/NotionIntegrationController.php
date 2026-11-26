<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Settings\Concerns\AuthorizesWorkspaceManagers;
use App\Http\Controllers\Settings\Concerns\RedirectsToIntegrationsHub;
use App\Models\CommerceIntegration;
use App\Models\Company;
use App\Services\Integrations\NotionIntegrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class NotionIntegrationController extends Controller
{
    use AuthorizesWorkspaceManagers;
    use RedirectsToIntegrationsHub;

    public function __construct(private NotionIntegrationService $notion) {}

    public function edit(Request $request): RedirectResponse
    {
        $this->authorizeWorkspaceManagers($request);
        abort_if(! $request->user()->company instanceof Company, 403);

        return $this->integrationsHub('notion');
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorizeWorkspaceManagers($request);
        $company = $request->user()->company;
        abort_if(! $company instanceof Company, 403);

        $data = $request->validate([
            'api_token' => ['nullable', 'string', 'max:512'],
            'database_id' => ['required', 'string', 'max:64'],
        ]);

        $existing = CommerceIntegration::query()->where('provider', NotionIntegrationService::PROVIDER)->first();
        if (blank($data['api_token'] ?? null) && blank($existing?->credentials['api_token'] ?? null)) {
            return back()->withInput()->withErrors(['api_token' => __('notion_api_token_required')]);
        }

        $this->notion->upsertIntegration($company, $data, [
            'sync_enabled' => $request->boolean('sync_enabled'),
        ]);

        return $this->integrationsHub('notion', __('notion_settings_saved'));
    }

    public function test(Request $request): RedirectResponse
    {
        $this->authorizeWorkspaceManagers($request);
        $company = $request->user()->company;
        abort_if(! $company instanceof Company, 403);

        try {
            $label = $this->notion->testConnection($company);

            return $this->integrationsHub('notion', __('notion_test_ok', ['name' => $label]));
        } catch (Throwable $e) {
            Log::warning('notion.test_failed', ['company_id' => $company->id, 'message' => $e->getMessage()]);

            return $this->integrationsHub('notion')->withErrors(['notion' => $e->getMessage()]);
        }
    }

    public function sync(Request $request): RedirectResponse
    {
        $this->authorizeWorkspaceManagers($request);
        $company = $request->user()->company;
        abort_if(! $company instanceof Company, 403);

        if (! $this->notion->isConfigured($company)) {
            return $this->integrationsHub('notion')->withErrors(['notion' => __('notion_not_configured')]);
        }

        try {
            $result = $this->notion->sync($company, $request->user());

            return $this->integrationsHub('notion', __('notion_sync_summary', [
                'pulled' => $result['pulled'],
                'pushed' => $result['pushed'],
            ]));
        } catch (Throwable $e) {
            Log::warning('notion.sync_failed', ['company_id' => $company->id, 'message' => $e->getMessage()]);

            return $this->integrationsHub('notion')->withErrors(['notion' => $e->getMessage()]);
        }
    }

    public function disconnect(Request $request): RedirectResponse
    {
        $this->authorizeWorkspaceManagers($request);
        $company = $request->user()->company;
        abort_if(! $company instanceof Company, 403);

        $this->notion->disconnect($company);

        return $this->integrationsHub('notion', __('notion_disconnected'));
    }
}
