<?php

namespace App\Http\Controllers\Commerce;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\CommerceOrder;
use App\Models\ProductVariant;
use App\Services\Commerce\CommerceInvoiceService;
use App\Services\Commerce\CommerceOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $this->guard($request);
        $orders = CommerceOrder::query()->with(['client', 'invoice'])->latest()->paginate(20);
        $paidRevenue = CommerceOrder::query()
            ->whereNotNull('invoice_id')
            ->whereHas('invoice', fn ($q) => $q->where('status', \App\Enums\InvoiceStatus::Paid))
            ->sum('total_minor');

        return view('commerce.orders.index', [
            'orders' => $orders,
            'paidRevenue' => (int) $paidRevenue,
        ]);
    }

    public function create(Request $request): View
    {
        $company = $this->guard($request);
        $variants = ProductVariant::query()->with('product')->whereHas('product', fn ($q) => $q->where('is_active', true))->orderBy('sku')->limit(200)->get();
        $clients = Client::query()->orderBy('name')->limit(200)->get();

        return view('commerce.orders.create', compact('variants', 'clients'));
    }

    public function store(Request $request, CommerceOrderService $orders): RedirectResponse
    {
        $company = $this->guard($request);
        $data = $request->validate([
            'client_id' => ['nullable', 'string'],
            'variant_id' => ['required', 'string'],
            'quantity' => ['required', 'integer', 'min:1'],
            'create_invoice' => ['nullable', 'boolean'],
            'create_delivery' => ['nullable', 'boolean'],
        ]);

        try {
            $order = $orders->createSale(
                $company,
                $request->user(),
                [['variant_id' => $data['variant_id'], 'quantity' => (int) $data['quantity']]],
                'manual',
                $data['client_id'] ?? null,
                null,
                null,
                $request->boolean('create_invoice'),
                $request->boolean('create_delivery'),
            );
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['order' => $e->getMessage()])->withInput();
        }

        return redirect()->route('commerce.orders.show', $order)->with('status', __('Order created.'));
    }

    public function show(Request $request, CommerceOrder $order): View
    {
        $this->guard($request);
        abort_if((string) $order->company_id !== (string) $request->user()->company_id, 404);
        $order->load(['items.variant.product', 'client', 'invoice', 'delivery']);

        return view('commerce.orders.show', compact('order'));
    }

    public function invoice(Request $request, CommerceOrder $order, CommerceInvoiceService $invoices): RedirectResponse
    {
        $this->guard($request);
        abort_if((string) $order->company_id !== (string) $request->user()->company_id, 404);
        $invoice = $invoices->createFromOrder($order);

        return redirect()->route('invoices.show', $invoice)->with('status', __('Invoice created.'));
    }

    private function guard(Request $request)
    {
        $user = $request->user();
        abort_if(! $user?->can('workspace.manage_commerce') && ! $user?->can('workspace.manage_invoices'), 403);
        abort_if(! $user->company, 403);

        return $user->company;
    }
}
