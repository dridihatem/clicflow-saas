<?php

namespace App\Http\Controllers;

use App\Models\MarketplaceModule;
use App\Services\FreeMarketplaceModuleActivationService;
use App\Services\MarketplaceCartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use RuntimeException;

class MarketplaceCartController extends Controller
{
    public function index(): RedirectResponse
    {
        return $this->redirectToModulesCart();
    }

    public function add(Request $request, MarketplaceModule $marketplaceModule, MarketplaceCartService $cart): RedirectResponse
    {
        abort_unless($marketplaceModule->is_published, 404);

        $activator = app(FreeMarketplaceModuleActivationService::class);
        if ($activator->isFree($marketplaceModule)) {
            $user = $request->user();
            if (! $user) {
                return redirect()
                    ->guest(route('login'))
                    ->with('status', __('settings_modules_free_activate_login'));
            }

            if (! $user->company || ! $user->hasRole('company_admin')) {
                return redirect()
                    ->route('settings.modules')
                    ->with('error', __('settings_modules_free_activate_admin_only'));
            }

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

        $supported = config('flowdesk.supported_currencies', ['EUR']);
        $supported = is_array($supported) ? $supported : ['EUR'];
        $currency = strtoupper((string) $request->input('currency', $cart->currency()));
        if (! in_array($currency, $supported, true)) {
            $currency = $cart->currency();
        }

        $cart->add($marketplaceModule, $currency);

        return $this->redirectToModulesCart('marketing_cart_added', ['name' => $marketplaceModule->name]);
    }

    public function remove(Request $request, MarketplaceCartService $cart): RedirectResponse
    {
        $data = $request->validate([
            'module_id' => ['required', 'string'],
        ]);

        $cart->remove($data['module_id']);

        return $this->redirectToModulesCart('marketing_cart_removed');
    }

    public function updateCurrency(Request $request, MarketplaceCartService $cart): RedirectResponse
    {
        $supported = config('flowdesk.supported_currencies', ['EUR']);
        $supported = is_array($supported) ? $supported : ['EUR'];

        $data = $request->validate([
            'currency' => ['required', 'string', 'size:3', 'in:'.implode(',', $supported)],
        ]);

        $cart->setCurrency($data['currency']);

        return $this->redirectToModulesCart();
    }

    private function redirectToModulesCart(?string $statusKey = null, array $replace = []): RedirectResponse
    {
        $redirect = redirect()->route('settings.modules', ['cart' => 1]);

        if ($statusKey) {
            $redirect->with('status', __($statusKey, $replace));
        }

        return $redirect;
    }
}
