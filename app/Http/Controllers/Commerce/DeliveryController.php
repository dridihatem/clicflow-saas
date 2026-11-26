<?php

namespace App\Http\Controllers\Commerce;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DeliveryController extends Controller
{
    public function index(Request $request): View
    {
        $this->guard($request);
        $deliveries = Delivery::query()->with('order')->latest()->paginate(20);

        return view('commerce.deliveries.index', compact('deliveries'));
    }

    public function update(Request $request, Delivery $delivery): RedirectResponse
    {
        $this->guard($request);
        abort_if((string) $delivery->company_id !== (string) $request->user()->company_id, 404);

        $data = $request->validate([
            'status' => ['required', 'in:pending,shipped,delivered,cancelled'],
            'carrier' => ['nullable', 'string', 'max:120'],
            'tracking_number' => ['nullable', 'string', 'max:120'],
        ]);

        $payload = [
            'status' => $data['status'],
            'carrier' => $data['carrier'] ?? $delivery->carrier,
            'tracking_number' => $data['tracking_number'] ?? $delivery->tracking_number,
        ];
        if ($data['status'] === 'shipped' && ! $delivery->shipped_at) {
            $payload['shipped_at'] = now();
        }
        if ($data['status'] === 'delivered') {
            $payload['delivered_at'] = now();
            $payload['shipped_at'] = $delivery->shipped_at ?? now();
        }

        $delivery->update($payload);

        return back()->with('status', __('Delivery updated.'));
    }

    private function guard(Request $request): void
    {
        $user = $request->user();
        abort_if(! $user?->can('workspace.manage_commerce') && ! $user?->can('workspace.manage_invoices'), 403);
    }
}
