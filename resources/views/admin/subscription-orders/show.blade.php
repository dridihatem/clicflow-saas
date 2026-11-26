<x-admin-layout :title="__('admin_subscription_orders_title')">
    <div class="mb-6">
        <a href="{{ route('admin.subscription-orders.index') }}" class="flow-secondary-button inline-flex items-center gap-2 rounded-lg border px-3 py-2 text-sm font-semibold shadow-sm transition">
            <i class="fa-solid fa-arrow-left text-xs" aria-hidden="true"></i>
            <span>{{ __('admin_subscription_orders_title') }}</span>
        </a>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ session('status') }}</div>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h1 class="text-lg font-semibold text-slate-900">{{ $order->order_number }}</h1>
            <dl class="mt-4 space-y-3 text-sm">
                <div class="flex justify-between gap-4"><dt class="text-slate-500">{{ __('Company') }}</dt><dd class="font-medium">{{ $order->company?->name }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-slate-500">{{ __('Plan') }}</dt><dd class="font-medium">{{ $order->plan?->name }} ({{ $order->period_months }} mo)</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-slate-500">{{ __('User') }}</dt><dd class="font-medium">{{ $order->user?->email }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-slate-500">{{ __('Method') }}</dt><dd class="font-medium">{{ $order->payment_method ?: '—' }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-slate-500">{{ __('Status') }}</dt><dd class="font-medium">{{ $order->status->label() }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-slate-500">{{ __('Total') }}</dt><dd class="font-bold tabular-nums">{{ flowdesk_format_minor($order->amount_minor, $order->currency) }} {{ $order->currency }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-slate-500">{{ __('External ref') }}</dt><dd class="font-mono text-xs">{{ $order->external_reference ?: '—' }}</dd></div>
            </dl>
        </div>

        @if ($order->isPending())
            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">{{ __('Update status') }}</h2>
                <p class="mt-2 text-sm text-slate-600">{{ __('admin_subscription_order_mark_paid_help') }}</p>
                <form method="POST" action="{{ route('admin.subscription-orders.status', $order) }}" class="mt-4">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="status" value="paid">
                    <button type="submit" class="inline-flex items-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">
                        {{ __('Mark as paid & activate plan') }}
                    </button>
                </form>
                <form method="POST" action="{{ route('admin.subscription-orders.status', $order) }}" class="mt-3">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="status" value="cancelled">
                    <button type="submit" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        {{ __('Cancel order') }}
                    </button>
                </form>
            </div>
        @endif
    </div>
</x-admin-layout>
