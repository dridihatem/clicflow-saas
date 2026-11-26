<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Settings\Concerns\RedirectsToIntegrationsHub;
use App\Jobs\SyncShopifyProductsJob;
use App\Services\Commerce\ShopifySyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ShopifyIntegrationController extends Controller
{
    use RedirectsToIntegrationsHub;

    public function edit(Request $request): RedirectResponse
    {
        $this->guard($request);

        return $this->integrationsHub('shopify');
    }

    public function update(Request $request, ShopifySyncService $shopify): RedirectResponse
    {
        $company = $this->guard($request);
        $data = $request->validate([
            'shop_domain' => ['required', 'string', 'max:255'],
            'access_token' => ['required', 'string', 'max:255'],
            'auto_push' => ['nullable', 'boolean'],
        ]);
        $shopify->upsertIntegration($company, $data, $request->boolean('auto_push', true));

        return $this->integrationsHub('shopify', __('Shopify connected.'));
    }

    public function sync(Request $request): RedirectResponse
    {
        $company = $this->guard($request);
        SyncShopifyProductsJob::dispatchSync((string) $company->id);

        return $this->integrationsHub('shopify', __('shopify_import_done'));
    }

    private function guard(Request $request)
    {
        $user = $request->user();
        abort_if(! $user?->can('workspace.manage_settings'), 403);
        abort_if(! $user->company, 403);

        return $user->company;
    }
}
