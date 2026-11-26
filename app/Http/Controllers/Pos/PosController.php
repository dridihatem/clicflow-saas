<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\MarketplaceModule;
use App\Models\PosSession;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Commerce\CommerceOrderService;
use App\Services\PlanLimitService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;

class PosController extends Controller
{
    public function index(Request $request, PlanLimitService $planLimits): View
    {
        $company = $this->guard($request);
        $currency = strtoupper((string) ($company->default_currency ?: flowdesk_default_currency()));
        $hasEcommerce = $planLimits->isFeatureEnabled($company, 'ecommerce');

        $session = PosSession::query()->whereNull('closed_at')->latest('opened_at')->first();
        $sessions = PosSession::query()->latest('opened_at')->limit(10)->get();

        $productCount = 0;
        $variants = collect();
        $catalog = [];

        if ($hasEcommerce) {
            $productCount = Product::query()->where('is_active', true)->count();
            $variants = ProductVariant::query()
                ->with('product')
                ->whereHas('product', fn ($q) => $q->where('is_active', true))
                ->orderBy('sku')
                ->limit(500)
                ->get();

            $catalog = $variants->map(fn (ProductVariant $variant) => [
                'id' => (string) $variant->id,
                'label' => $variant->label(),
                'product' => (string) ($variant->product?->name ?? ''),
                'sku' => (string) ($variant->sku ?? ''),
                'color' => (string) ($variant->color ?? ''),
                'size' => (string) ($variant->size ?? ''),
                'stock' => (int) $variant->stock_qty,
                'track_stock' => (bool) ($variant->product?->track_stock ?? false),
                'price_minor' => $variant->unitPriceMinor(),
                'price_label' => flowdesk_format_minor($variant->unitPriceMinor(), $currency),
            ])->values()->all();
        }

        $ecommerceModule = MarketplaceModule::query()
            ->where('slug', 'ecommerce')
            ->where('is_published', true)
            ->first();

        return view('pos.index', [
            'session' => $session,
            'sessions' => $sessions,
            'variants' => $variants,
            'catalog' => $catalog,
            'currency' => $currency,
            'hasEcommerce' => $hasEcommerce,
            'productCount' => $productCount,
            'ecommerceModuleUrl' => $ecommerceModule
                ? route('modules.show', ['slug' => $ecommerceModule->slug])
                : route('settings.modules'),
            'productsUrl' => $hasEcommerce && \Illuminate\Support\Facades\Route::has('commerce.products.index')
                ? route('commerce.products.index')
                : null,
            'createProductUrl' => $hasEcommerce && \Illuminate\Support\Facades\Route::has('commerce.products.create')
                ? route('commerce.products.create')
                : null,
        ]);
    }

    public function open(Request $request): RedirectResponse
    {
        $company = $this->guard($request);
        $this->requireEcommerce($request);

        if (PosSession::query()->whereNull('closed_at')->exists()) {
            return back()->withErrors(['pos' => __('A POS session is already open.')]);
        }

        $data = $request->validate([
            'opening_cash' => ['nullable', 'numeric', 'min:0'],
        ]);

        PosSession::query()->create([
            'company_id' => $company->id,
            'opened_by' => $request->user()->id,
            'opened_at' => now(),
            'opening_cash_minor' => flowdesk_decimal_to_minor((string) ($data['opening_cash'] ?? '0'), $company->default_currency ?: flowdesk_default_currency()),
        ]);

        return back()->with('status', __('POS session opened.'));
    }

    public function sell(Request $request, CommerceOrderService $orders): RedirectResponse
    {
        $company = $this->guard($request);
        $this->requireEcommerce($request);

        $session = PosSession::query()->whereNull('closed_at')->latest('opened_at')->first();
        abort_if(! $session, 422, __('Open a POS session first.'));

        $data = $request->validate([
            'variant_id' => ['nullable', 'string'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'lines' => ['nullable', 'array', 'min:1'],
            'lines.*.variant_id' => ['required_with:lines', 'string'],
            'lines.*.quantity' => ['required_with:lines', 'integer', 'min:1'],
            'payment_method' => ['required', 'in:cash,card'],
            'create_invoice' => ['nullable', 'boolean'],
        ]);

        $lines = $this->normalizeSaleLines($data);
        if ($lines === []) {
            throw ValidationException::withMessages([
                'pos' => __('Add at least one product to the cart.'),
            ]);
        }

        try {
            $orders->createSale(
                $company,
                $request->user(),
                $lines,
                'pos',
                null,
                $data['payment_method'],
                $session,
                $request->boolean('create_invoice', true),
                false,
            );
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['pos' => $e->getMessage()]);
        }

        return back()->with('status', __('Sale completed.'));
    }

    public function close(Request $request, PosSession $posSession): RedirectResponse
    {
        $company = $this->guard($request);
        abort_if((string) $posSession->company_id !== (string) $company->id, 404);
        abort_unless($posSession->isOpen(), 422);

        $data = $request->validate([
            'closing_cash' => ['nullable', 'numeric', 'min:0'],
        ]);

        $posSession->update([
            'closed_at' => now(),
            'closing_cash_minor' => flowdesk_decimal_to_minor((string) ($data['closing_cash'] ?? '0'), $company->default_currency ?: flowdesk_default_currency()),
        ]);

        return back()->with('status', __('POS session closed.'));
    }

    private function requireEcommerce(Request $request): void
    {
        $company = $request->user()?->company;
        abort_if(! $company, 403);

        if (! app(PlanLimitService::class)->isFeatureEnabled($company, 'ecommerce')) {
            throw ValidationException::withMessages([
                'pos' => __('POS requires the Products module. Add the E-commerce pack (products, inventory & POS) from the marketplace.'),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array{variant_id: string, quantity: int}>
     */
    private function normalizeSaleLines(array $data): array
    {
        if (! empty($data['lines']) && is_array($data['lines'])) {
            $merged = [];
            foreach ($data['lines'] as $line) {
                $id = (string) ($line['variant_id'] ?? '');
                $qty = (int) ($line['quantity'] ?? 0);
                if ($id === '' || $qty < 1) {
                    continue;
                }
                $merged[$id] = ($merged[$id] ?? 0) + $qty;
            }

            return collect($merged)
                ->map(fn (int $qty, string $id) => ['variant_id' => $id, 'quantity' => $qty])
                ->values()
                ->all();
        }

        if (! empty($data['variant_id'])) {
            return [[
                'variant_id' => (string) $data['variant_id'],
                'quantity' => max(1, (int) ($data['quantity'] ?? 1)),
            ]];
        }

        return [];
    }

    private function guard(Request $request)
    {
        $user = $request->user();
        abort_if(! $user?->can('workspace.manage_commerce') && ! $user?->can('workspace.manage_invoices'), 403);
        abort_if(! $user->company, 403);

        return $user->company;
    }
}
