<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Settings\Concerns\RedirectsToIntegrationsHub;
use App\Jobs\SyncWooCommerceProductsJob;
use App\Services\Commerce\WooCommerceSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WooCommerceIntegrationController extends Controller
{
    use RedirectsToIntegrationsHub;

    public function edit(Request $request): RedirectResponse
    {
        $this->guard($request);

        return $this->integrationsHub('woocommerce');
    }

    public function update(Request $request, WooCommerceSyncService $woo): RedirectResponse
    {
        $company = $this->guard($request);
        $data = $request->validate([
            'store_url' => ['required', 'url', 'max:255'],
            'consumer_key' => ['required', 'string', 'max:255'],
            'consumer_secret' => ['required', 'string', 'max:255'],
            'auto_push' => ['nullable', 'boolean'],
        ]);
        $woo->upsertIntegration($company, $data, $request->boolean('auto_push', true));

        return $this->integrationsHub('woocommerce', __('WooCommerce connected.'));
    }

    public function sync(Request $request): RedirectResponse
    {
        $company = $this->guard($request);
        SyncWooCommerceProductsJob::dispatchSync((string) $company->id);

        return $this->integrationsHub('woocommerce', __('woocommerce_import_done'));
    }

    private function guard(Request $request)
    {
        $user = $request->user();
        abort_if(! $user?->can('workspace.manage_settings'), 403);
        abort_if(! $user->company, 403);

        return $user->company;
    }
}
