<?php

namespace App\Http\Controllers\Admin;

use App\Enums\WorkspaceSubscriptionOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\WorkspaceSubscriptionOrder;
use App\Services\WorkspaceSubscriptionCheckoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WorkspaceSubscriptionOrderController extends Controller
{
    public function index(Request $request): View
    {
        $status = (string) $request->query('status', '');
        if ($status !== '' && WorkspaceSubscriptionOrderStatus::tryFrom($status) === null) {
            $status = '';
        }

        $reference = trim((string) $request->query('reference', ''));

        $orders = WorkspaceSubscriptionOrder::query()
            ->withoutGlobalScopes()
            ->with(['company', 'plan', 'user'])
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($reference !== '', function ($query) use ($reference): void {
                $query->where(function ($inner) use ($reference): void {
                    $inner->where('order_number', 'like', '%'.$reference.'%')
                        ->orWhere('external_reference', 'like', '%'.$reference.'%');
                });
            })
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('admin.subscription-orders.index', [
            'orders' => $orders,
            'selectedStatus' => $status,
            'selectedReference' => $reference,
            'statuses' => WorkspaceSubscriptionOrderStatus::cases(),
        ]);
    }

    public function show(WorkspaceSubscriptionOrder $subscriptionOrder): View
    {
        $subscriptionOrder->load(['company', 'plan', 'user']);

        return view('admin.subscription-orders.show', [
            'order' => $subscriptionOrder,
        ]);
    }

    public function updateStatus(
        Request $request,
        WorkspaceSubscriptionOrder $subscriptionOrder,
        WorkspaceSubscriptionCheckoutService $checkout,
    ): RedirectResponse {
        $data = $request->validate([
            'status' => ['required', Rule::enum(WorkspaceSubscriptionOrderStatus::class)],
        ]);

        $status = WorkspaceSubscriptionOrderStatus::from($data['status']);

        if ($status === WorkspaceSubscriptionOrderStatus::Paid) {
            $checkout->markPaid($subscriptionOrder, $subscriptionOrder->payment_method ?: 'bank');
        } else {
            $subscriptionOrder->forceFill(['status' => $status])->save();
        }

        return redirect()
            ->route('admin.subscription-orders.show', $subscriptionOrder)
            ->with('status', __('admin_subscription_order_status_updated'));
    }
}
