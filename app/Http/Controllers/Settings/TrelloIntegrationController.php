<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Settings\Concerns\AuthorizesWorkspaceManagers;
use App\Http\Controllers\Settings\Concerns\RedirectsToIntegrationsHub;
use App\Models\CommerceIntegration;
use App\Models\Company;
use App\Services\Integrations\TrelloProjectSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class TrelloIntegrationController extends Controller
{
    use AuthorizesWorkspaceManagers;
    use RedirectsToIntegrationsHub;

    public function __construct(private TrelloProjectSyncService $trello) {}

    public function edit(Request $request): RedirectResponse
    {
        $this->authorizeWorkspaceManagers($request);
        abort_if(! $request->user()->company instanceof Company, 403);

        return $this->integrationsHub('trello');
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorizeWorkspaceManagers($request);
        $company = $request->user()->company;
        abort_if(! $company instanceof Company, 403);

        $data = $request->validate([
            'api_key' => ['nullable', 'string', 'max:128'],
            'api_token' => ['nullable', 'string', 'max:256'],
        ]);

        $existing = CommerceIntegration::query()->where('provider', TrelloProjectSyncService::PROVIDER)->first();
        if (blank($data['api_key'] ?? null) && blank($existing?->credentials['api_key'] ?? null)) {
            return back()->withInput()->withErrors(['api_key' => __('trello_api_key_required')]);
        }
        if (blank($data['api_token'] ?? null) && blank($existing?->credentials['api_token'] ?? null)) {
            return back()->withInput()->withErrors(['api_token' => __('trello_api_token_required')]);
        }

        $this->trello->upsertIntegration($company, $data, [
            'sync_enabled' => $request->boolean('sync_enabled'),
        ]);

        return $this->integrationsHub('trello', __('trello_settings_saved'));
    }

    public function test(Request $request): RedirectResponse
    {
        $this->authorizeWorkspaceManagers($request);
        $company = $request->user()->company;
        abort_if(! $company instanceof Company, 403);

        try {
            $label = $this->trello->testConnection($company);

            return $this->integrationsHub('trello', __('trello_test_ok', ['name' => $label]));
        } catch (Throwable $e) {
            Log::warning('trello.test_failed', ['company_id' => $company->id, 'message' => $e->getMessage()]);

            return $this->integrationsHub('trello')->withErrors(['trello' => $e->getMessage()]);
        }
    }

    public function sync(Request $request): RedirectResponse
    {
        $this->authorizeWorkspaceManagers($request);
        $company = $request->user()->company;
        abort_if(! $company instanceof Company, 403);

        if (! $this->trello->isConfigured($company)) {
            return $this->integrationsHub('trello')->withErrors(['trello' => __('trello_not_configured')]);
        }

        try {
            $result = $this->trello->sync($company, $request->user());

            return $this->integrationsHub('trello', __('trello_sync_summary', [
                'pulled' => $result['pulled'],
                'pushed' => $result['pushed'],
            ]));
        } catch (Throwable $e) {
            Log::warning('trello.sync_failed', ['company_id' => $company->id, 'message' => $e->getMessage()]);

            return $this->integrationsHub('trello')->withErrors(['trello' => $e->getMessage()]);
        }
    }

    public function disconnect(Request $request): RedirectResponse
    {
        $this->authorizeWorkspaceManagers($request);
        $company = $request->user()->company;
        abort_if(! $company instanceof Company, 403);

        $this->trello->disconnect($company);

        return $this->integrationsHub('trello', __('trello_disconnected'));
    }
}
