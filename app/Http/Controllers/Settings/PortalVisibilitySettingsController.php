<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Services\PortalVisibilityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PortalVisibilitySettingsController extends Controller
{
    public function __construct()
    {
        $this->middleware(function (Request $request, $next) {
            abort_if(! $request->user()?->hasAnyRole(['company_admin', 'team_member']), 403);
            abort_if(! $request->user()->company, 403);

            return $next($request);
        });
    }

    public function edit(Request $request, PortalVisibilityService $visibility): View
    {
        $company = $request->user()->company;

        return view('settings.portal-visibility', [
            'sections' => $visibility->sections(),
            'hidden' => $visibility->companyHidden($company),
            'overrideCount' => Client::query()->withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->whereNotNull('portal_visibility')
                ->count(),
        ]);
    }

    public function update(Request $request, PortalVisibilityService $visibility): RedirectResponse
    {
        $data = $request->validate([
            'visible' => ['nullable', 'array'],
            'visible.*' => ['string', Rule::in($visibility->keys())],
        ]);

        $visible = $data['visible'] ?? [];
        $hidden = array_values(array_diff($visibility->keys(), $visible));
        $visibility->saveCompanyHidden($request->user()->company, $hidden);

        return redirect()->route('settings.portal-visibility')->with('status', __('portal_vis_saved'));
    }

    public function updateClient(Request $request, Client $client, PortalVisibilityService $visibility): RedirectResponse
    {
        $company = $request->user()->company;
        abort_if((string) $client->company_id !== (string) $company->id, 404);

        $data = $request->validate([
            'override' => ['nullable', 'array'],
            'override.*' => ['string', Rule::in(['inherit', 'show', 'hide'])],
        ]);

        $visibility->saveClientOverrides($client, $data['override'] ?? []);

        return redirect()->to(route('clients.show', ['client' => $client, 'tab' => 'portal']).'#portail-visibilite')->with('status', __('portal_vis_client_saved'));
    }
}
