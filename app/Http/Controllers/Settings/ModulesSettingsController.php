<?php

namespace App\Http\Controllers\Settings;

use App\Enums\MarketplaceModuleCategory;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Settings\Concerns\AuthorizesWorkspaceManagers;
use App\Models\Company;
use App\Models\InstalledModule;
use App\Models\MarketplaceModule;
use App\Models\MarketplaceOrderItem;
use App\Services\FreeMarketplaceModuleActivationService;
use App\Services\MarketplaceCartService;
use App\Services\MarketplaceModuleZipService;
use App\Services\MarketplaceOrderFulfillmentService;
use App\Services\ModuleEntitlementService;
use App\Services\ModuleInstallerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ModulesSettingsController extends Controller
{
    use AuthorizesWorkspaceManagers;

    public function index(Request $request, MarketplaceOrderFulfillmentService $fulfillment): View
    {
        $user = $this->authorizeWorkspaceManagers($request);
        $company = $user->company;
        $entitlements = app(ModuleEntitlementService::class);

        $modules = InstalledModule::query()
            ->orderBy('name')
            ->get()
            ->reject(fn (InstalledModule $module): bool => $entitlements->isCoreBundledSlug((string) $module->slug))
            ->values();

        $purchasedModules = $company
            ? $fulfillment->purchasedModulesForCompany($company)
                ->map(function (array $purchase) use ($entitlements): array {
                    $slug = (string) ($purchase['module']?->slug ?: $purchase['item']->module_slug ?? '');
                    $purchase['is_core'] = $entitlements->isCoreBundledSlug($slug);
                    $purchase['settings_route'] = match (strtolower($slug)) {
                        'odoo-sync', 'odoo' => 'settings.odoo',
                        'shopify-sync', 'shopify' => 'settings.shopify',
                        'woocommerce-sync', 'woocommerce' => 'settings.woocommerce',
                        default => null,
                    };

                    return $purchase;
                })
                ->values()
            : collect();

        $catalog = $company instanceof Company
            ? $this->marketplaceCatalog($company, $entitlements, $fulfillment)
            : collect();

        $catalogByCategory = $catalog
            ->groupBy(function (array $item): string {
                $category = $item['module']->category;

                return $category instanceof MarketplaceModuleCategory
                    ? $category->value
                    : MarketplaceModuleCategory::General->value;
            })
            ->sortKeysUsing(function (string $left, string $right): int {
                $leftOrder = MarketplaceModuleCategory::tryFrom($left)?->sortOrder() ?? 999;
                $rightOrder = MarketplaceModuleCategory::tryFrom($right)?->sortOrder() ?? 999;

                return $leftOrder <=> $rightOrder;
            });

        $cart = app(MarketplaceCartService::class);
        $supported = config('flowdesk.supported_currencies', ['EUR']);
        $supported = is_array($supported) ? $supported : ['EUR'];

        return view('settings.modules', [
            'modules' => $modules,
            'purchasedModules' => $purchasedModules,
            'catalog' => $catalog,
            'catalogByCategory' => $catalogByCategory,
            'cartCount' => $cart->count(),
            'cartLineItems' => $cart->lineItems(),
            'cartTotalMinor' => $cart->totalMinor(),
            'cartCurrency' => $cart->currency(),
            'supportedCurrencies' => $supported,
            'currencyLabels' => is_array(config('flowdesk.currency_labels')) ? config('flowdesk.currency_labels') : [],
            'openCart' => $request->boolean('cart'),
            'canManage' => $user->hasRole('company_admin'),
        ]);
    }

    public function activateFree(
        Request $request,
        MarketplaceModule $marketplaceModule,
        FreeMarketplaceModuleActivationService $activator,
    ): RedirectResponse {
        $user = $this->authorizeWorkspaceManagers($request);
        abort_if(! $user->hasRole('company_admin'), 403);

        try {
            $activator->activate($user, $marketplaceModule);
        } catch (RuntimeException $e) {
            return redirect()
                ->route('settings.modules')
                ->with('error', $e->getMessage());
        }

        $open = $activator->openRoute($marketplaceModule);
        if ($open && Route::has($open[0])) {
            return redirect()
                ->route($open[0], $open[1])
                ->with('status', __('settings_modules_free_activated', ['name' => $marketplaceModule->name]));
        }

        return redirect()
            ->route('settings.modules')
            ->with('status', __('settings_modules_free_activated', ['name' => $marketplaceModule->name]));
    }

    public function install(Request $request, ModuleInstallerService $installer): RedirectResponse
    {
        $user = $this->authorizeWorkspaceManagers($request);
        abort_if(! $user->hasRole('company_admin'), 403);

        $company = $user->company;
        abort_if(! $company, 403);

        $request->validate([
            'module_zip' => ['required', 'file', 'mimes:zip', 'max:15360'],
        ]);

        try {
            $module = $installer->installFromZip($company, $request->file('module_zip'));
        } catch (RuntimeException $e) {
            return redirect()
                ->route('settings.modules')
                ->with('error', $e->getMessage());
        }

        $entitlements = app(ModuleEntitlementService::class);
        if ($entitlements->isCoreBundledSlug((string) $module->slug)) {
            $entitlements->grantFromMarketplaceSlug($company, (string) $module->slug);
            $open = app(FreeMarketplaceModuleActivationService::class)->openRoute((string) $module->slug);

            // Companion zips unlock core features; they are intentionally hidden from the CRM modules list.
            if ($open && Route::has($open[0])) {
                return redirect()
                    ->route($open[0], $open[1])
                    ->with('status', __('settings_modules_core_feature_unlocked', ['name' => $module->name]));
            }

            return redirect()
                ->route('settings.modules')
                ->with('status', __('settings_modules_core_feature_unlocked', ['name' => $module->name]));
        }

        return redirect()
            ->route('modules.show', $module->slug)
            ->with('status', __('modules_installed_ok'));
    }

    public function installPurchased(
        Request $request,
        MarketplaceModule $marketplaceModule,
        MarketplaceOrderFulfillmentService $fulfillment,
        MarketplaceModuleZipService $zipService,
        ModuleInstallerService $installer,
    ): RedirectResponse {
        $user = $this->authorizeWorkspaceManagers($request);
        abort_if(! $user->hasRole('company_admin'), 403);

        $company = $user->company;
        abort_if(! $company, 403);

        abort_unless($fulfillment->companyOwnsPaidModule($company, $marketplaceModule->id), 403);

        $entitlements = app(ModuleEntitlementService::class);
        if ($entitlements->isCoreBundledSlug((string) $marketplaceModule->slug)) {
            $entitlements->grantFromMarketplaceSlug($company, (string) $marketplaceModule->slug);

            return redirect()
                ->route('settings.modules')
                ->with('status', __('settings_modules_core_feature_unlocked', ['name' => $marketplaceModule->name]));
        }

        $zipPath = $zipService->absolutePath($marketplaceModule);
        if ($zipPath === null) {
            return redirect()
                ->route('settings.modules')
                ->with('error', __('marketing_checkout_no_zip'));
        }

        try {
            $installer->installFromStoredZip($company, $zipPath);
        } catch (RuntimeException $e) {
            return redirect()
                ->route('settings.modules')
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('settings.modules')
            ->with('status', __('settings_modules_purchased_installed', ['name' => $marketplaceModule->name]));
    }

    public function downloadPurchased(
        Request $request,
        MarketplaceModule $marketplaceModule,
        MarketplaceOrderFulfillmentService $fulfillment,
        MarketplaceModuleZipService $zipService,
    ): StreamedResponse {
        $user = $this->authorizeWorkspaceManagers($request);
        $company = $user->company;
        abort_if(! $company, 403);

        abort_unless($fulfillment->companyOwnsPaidModule($company, $marketplaceModule->id), 403);

        $zipPath = $zipService->absolutePath($marketplaceModule);
        abort_if($zipPath === null || ! is_file($zipPath), 404);

        $filename = $marketplaceModule->slug.'.zip';

        return response()->streamDownload(
            fn () => readfile($zipPath),
            $filename,
            ['Content-Type' => 'application/zip'],
        );
    }

    public function destroyPurchased(
        Request $request,
        MarketplaceOrderItem $marketplaceOrderItem,
        MarketplaceOrderFulfillmentService $fulfillment,
        ModuleInstallerService $installer,
    ): RedirectResponse {
        $user = $this->authorizeWorkspaceManagers($request);
        abort_if(! $user->hasRole('company_admin'), 403);

        $company = $user->company;
        abort_if(! $company, 403);

        $marketplaceOrderItem->loadMissing('order');
        $order = $marketplaceOrderItem->order;
        abort_if($order === null, 404);

        abort_unless(
            $fulfillment->companyOwnsPaidModuleSlug($company, $marketplaceOrderItem->module_slug),
            403,
        );

        $installed = InstalledModule::query()
            ->where('company_id', $company->id)
            ->where('slug', $marketplaceOrderItem->module_slug)
            ->first();

        if ($installed) {
            $installer->uninstall($installed);
        }

        $fulfillment->dismissPurchasedModule(
            $company,
            $marketplaceOrderItem->module_slug,
            $marketplaceOrderItem->marketplace_module_id,
        );

        return redirect()
            ->route('settings.modules')
            ->with('status', __('settings_modules_purchased_removed', ['name' => $marketplaceOrderItem->module_name]));
    }

    public function toggle(Request $request, InstalledModule $module, ModuleInstallerService $installer): RedirectResponse
    {
        $this->authorizeWorkspaceManagers($request);
        abort_if((string) $module->company_id !== (string) $request->user()->company_id, 403);

        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        $installer->setEnabled($module, (bool) $data['enabled']);

        return redirect()
            ->route('settings.modules')
            ->with('status', __('modules_status_updated'));
    }

    public function destroy(Request $request, InstalledModule $module, ModuleInstallerService $installer): RedirectResponse
    {
        $user = $this->authorizeWorkspaceManagers($request);
        abort_if(! $user->hasRole('company_admin'), 403);
        abort_if((string) $module->company_id !== (string) $user->company_id, 403);

        $installer->uninstall($module);

        return redirect()
            ->route('settings.modules')
            ->with('status', __('modules_uninstalled_ok'));
    }

    /**
     * @return Collection<int, array{
     *   module: MarketplaceModule,
     *   is_free: bool,
     *   is_core: bool,
     *   is_owned: bool,
     *   is_installed: bool,
     *   settings_route: string|null,
     *   open_route: array{0: string, 1: array<string, mixed>}|null
     * }>
     */
    private function marketplaceCatalog(
        Company $company,
        ModuleEntitlementService $entitlements,
        MarketplaceOrderFulfillmentService $fulfillment,
    ): Collection {
        $activator = app(FreeMarketplaceModuleActivationService::class);

        return MarketplaceModule::query()
            ->where('is_published', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(function (MarketplaceModule $module) use ($company, $entitlements, $fulfillment, $activator): array {
                $slug = (string) $module->slug;
                $isCore = $entitlements->isCoreBundledSlug($slug);
                $isOwned = $fulfillment->companyOwnsPaidModule($company, $module->id)
                    || $activator->companyAlreadyOwns($company, $module);
                $isInstalled = $activator->isInstalled($company, $module);
                $settingsRoute = $activator->settingsRouteFor($module);

                return [
                    'module' => $module,
                    'is_free' => $activator->isFree($module),
                    'is_core' => $isCore,
                    'is_owned' => $isOwned,
                    'is_installed' => $isInstalled,
                    'settings_route' => ($settingsRoute && Route::has($settingsRoute)) ? $settingsRoute : null,
                    'open_route' => $activator->openRoute($module),
                ];
            })
            ->values();
    }
}
