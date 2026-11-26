<?php

namespace App\Http\Controllers\Commerce;

use App\Enums\ProductKind;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Commerce\CommerceSyncDispatcher;
use App\Services\Commerce\ProductDeletionService;
use App\Services\Commerce\StockService;
use App\Support\CatalogUnits;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeCommerce($request);

        $kind = $request->string('kind')->trim()->toString();
        if (! in_array($kind, ['product', 'service'], true)) {
            $kind = '';
        }

        $products = Product::query()
            ->with('variants')
            ->when($kind !== '', fn ($q) => $q->where('kind', $kind))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('commerce.products.index', compact('products', 'kind'));
    }

    public function create(Request $request): View
    {
        $this->authorizeCommerce($request);

        return view('commerce.products.create');
    }

    public function store(Request $request, CommerceSyncDispatcher $sync): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $company = $this->authorizeCommerce($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:64'],
            'description' => ['nullable', 'string'],
            'base_price' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'kind' => ['nullable', 'string', Rule::in(['product', 'service'])],
            'unit' => ['nullable', 'string', 'max:32'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tax_rate_2' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'category' => ['nullable', 'string', 'max:100'],
            'track_stock' => ['nullable', 'boolean'],
            'color' => ['nullable', 'string', 'max:64'],
            'size' => ['nullable', 'string', 'max:64'],
            'stock_qty' => ['nullable', 'integer'],
        ]);

        $kind = $data['kind'] ?? ProductKind::Product->value;
        $unit = $this->normalizedUnit($data['unit'] ?? null);
        $trackStock = $kind === ProductKind::Service->value ? false : $request->boolean('track_stock', true);

        $product = Product::query()->create([
            'company_id' => $company->id,
            'name' => $data['name'],
            'kind' => $kind,
            'sku' => $data['sku'] ?? null,
            'description' => $data['description'] ?? null,
            'base_price_minor' => flowdesk_decimal_to_minor((string) $data['base_price'], $data['currency']),
            'currency' => strtoupper($data['currency']),
            'unit' => $unit,
            'track_stock' => $trackStock,
            'tax_rate' => $this->taxValue($data['tax_rate'] ?? null),
            'tax_rate_2' => $this->taxValue($data['tax_rate_2'] ?? null),
            'category' => filled($data['category'] ?? null) ? trim((string) $data['category']) : null,
            'is_active' => true,
        ]);

        $variant = ProductVariant::query()->create([
            'company_id' => $company->id,
            'product_id' => $product->id,
            'sku' => $data['sku'] ?? null,
            'color' => $data['color'] ?? null,
            'size' => $data['size'] ?? null,
            'price_minor' => null,
            'stock_qty' => (int) ($data['stock_qty'] ?? 0),
        ]);

        $sync->pushVariantIfNeeded($company, $variant);

        if ($request->expectsJson()) {
            // Quick add from an invoice / quote line: return the catalog entry used by the line picker.
            $entry = flowdesk_document_catalog_for_js(collect([$product->load('variants')]))[0] ?? null;

            return response()->json(['ok' => true, 'product' => $entry, 'message' => __('commerce_product_created')], 201);
        }

        return redirect()->route('commerce.products.show', $product)->with('status', __('commerce_product_created'));
    }

    public function show(Request $request, Product $product): View
    {
        $this->authorizeCommerce($request);
        abort_if((string) $product->company_id !== (string) $request->user()->company_id, 404);
        $product->load('variants');

        return view('commerce.products.show', compact('product'));
    }

    public function update(Request $request, Product $product, CommerceSyncDispatcher $sync): RedirectResponse
    {
        $company = $this->authorizeCommerce($request);
        abort_if((string) $product->company_id !== (string) $company->id, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:64'],
            'description' => ['nullable', 'string'],
            'base_price' => ['required', 'numeric', 'min:0'],
            'kind' => ['nullable', 'string', Rule::in(['product', 'service'])],
            'unit' => ['nullable', 'string', 'max:32'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tax_rate_2' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'category' => ['nullable', 'string', 'max:100'],
            'track_stock' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $kind = $data['kind'] ?? ($product->kind?->value ?? ProductKind::Product->value);
        $unit = $this->normalizedUnit($data['unit'] ?? null);
        $trackStock = $kind === ProductKind::Service->value
            ? false
            : $request->boolean('track_stock', (bool) $product->track_stock);

        $product->update([
            'name' => $data['name'],
            'sku' => $data['sku'] ?? null,
            'description' => $data['description'] ?? null,
            'kind' => $kind,
            'unit' => $unit,
            'track_stock' => $trackStock,
            'base_price_minor' => flowdesk_decimal_to_minor((string) $data['base_price'], $product->currency),
            'tax_rate' => $this->taxValue($data['tax_rate'] ?? null),
            'tax_rate_2' => $this->taxValue($data['tax_rate_2'] ?? null),
            'category' => filled($data['category'] ?? null) ? trim((string) $data['category']) : null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        foreach ($product->variants as $variant) {
            $sync->pushVariantIfNeeded($company, $variant);
        }

        return back()->with('status', __('commerce_product_updated'));
    }

    public function storeVariant(Request $request, Product $product, CommerceSyncDispatcher $sync): RedirectResponse
    {
        $company = $this->authorizeCommerce($request);
        abort_if((string) $product->company_id !== (string) $company->id, 404);

        $data = $request->validate([
            'sku' => ['nullable', 'string', 'max:64'],
            'color' => ['nullable', 'string', 'max:64'],
            'size' => ['nullable', 'string', 'max:64'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'stock_qty' => ['nullable', 'integer'],
        ]);

        $variant = ProductVariant::query()->create([
            'company_id' => $company->id,
            'product_id' => $product->id,
            'sku' => $data['sku'] ?? null,
            'color' => $data['color'] ?? null,
            'size' => $data['size'] ?? null,
            'price_minor' => isset($data['price'])
                ? flowdesk_decimal_to_minor((string) $data['price'], $product->currency)
                : null,
            'stock_qty' => (int) ($data['stock_qty'] ?? 0),
        ]);

        $sync->pushVariantIfNeeded($company, $variant);

        return back()->with('status', __('commerce_variant_added'));
    }

    public function adjustStock(Request $request, ProductVariant $variant, StockService $stock, CommerceSyncDispatcher $sync): RedirectResponse
    {
        $company = $this->authorizeCommerce($request);
        abort_if((string) $variant->company_id !== (string) $company->id, 404);

        $data = $request->validate([
            'delta' => ['required', 'integer', 'not_in:0'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $stock->adjust($company, $variant, (int) $data['delta'], 'adjust', null, null, $data['notes'] ?? null);
            $sync->pushVariantIfNeeded($company, $variant->fresh());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['stock' => $e->getMessage()]);
        }

        return back()->with('status', __('commerce_stock_updated'));
    }

    public function destroy(Request $request, Product $product, ProductDeletionService $deleter): RedirectResponse
    {
        $company = $this->authorizeCommerce($request);
        abort_unless((string) $product->company_id === (string) $company->id, 404);

        $result = $deleter->deleteMany($company, [(string) $product->id], $request->user()->id);

        return redirect()->route('commerce.products.index')
            ->with($result['deleted'] === [] ? 'error' : 'status', $deleter->summary($result));
    }

    public function bulkDestroy(Request $request, ProductDeletionService $deleter): RedirectResponse
    {
        $company = $this->authorizeCommerce($request);

        $data = $request->validate([
            'ids' => ['required_without:all_matching', 'array', 'max:500'],
            'ids.*' => ['string', 'max:40'],
            'all_matching' => ['nullable', 'boolean'],
            'kind' => ['nullable', Rule::in(['product', 'service'])],
        ]);

        if ($request->boolean('all_matching')) {
            $ids = Product::query()->withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->when(filled($data['kind'] ?? null), fn ($q) => $q->where('kind', $data['kind']))
                ->limit(2000)
                ->pluck('id')->map(fn ($id) => (string) $id)->all();
        } else {
            $ids = $data['ids'] ?? [];
        }

        $result = $deleter->deleteMany($company, $ids, $request->user()->id);

        return redirect()->route('commerce.products.index', array_filter(['kind' => $data['kind'] ?? null]))
            ->with($result['deleted'] === [] ? 'error' : 'status', $deleter->summary($result));
    }

    private function authorizeCommerce(Request $request)
    {
        $user = $request->user();
        abort_if(! $user?->can('workspace.manage_commerce') && ! $user?->can('workspace.manage_invoices'), 403);
        abort_if(! $user->company, 403);

        return $user->company;
    }

    private function normalizedUnit(mixed $value): ?string
    {
        $unit = is_string($value) ? trim($value) : '';
        if ($unit === '' || $unit === CatalogUnits::CUSTOM) {
            return null;
        }

        return mb_substr($unit, 0, 32);
    }

    private function taxValue(mixed $value): ?float
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return round((float) str_replace(',', '.', (string) $value), 2);
    }
}
