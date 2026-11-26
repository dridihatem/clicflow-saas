@props([
    'paymentQr' => null,
    'compact' => false,
    'thumb' => false,
])

@if (is_array($paymentQr) && ($paymentQr['data_uri'] ?? null))
    @php
        $isThumb = $thumb || $compact;
        $qrSrc = $paymentQr['data_uri'];
        $qrUrl = $paymentQr['url'] ?? null;
    @endphp
    <div
        class="shrink-0"
        x-data="{ open: false }"
        @keydown.escape.window="open = false"
    >
        <button
            type="button"
            class="group flex flex-col items-center text-center"
            @click="open = true"
            title="{{ __('invoice_qr_enlarge') }}"
            aria-label="{{ __('invoice_qr_enlarge') }}"
        >
            <span @class([
                'overflow-hidden rounded-lg bg-white p-0.5 ring-1 ring-slate-200 transition group-hover:ring-indigo-400 dark:ring-slate-600 dark:group-hover:ring-indigo-500',
                'h-14 w-14' => $isThumb,
                'h-16 w-16' => ! $isThumb,
            ])>
                <img
                    src="{{ $qrSrc }}"
                    alt="{{ __('invoice_pdf_scan_to_pay') }}"
                    class="h-full w-full object-contain"
                />
            </span>
            <span class="mt-1 text-[10px] font-medium leading-tight text-slate-500 group-hover:text-indigo-600 dark:text-slate-400 dark:group-hover:text-indigo-300">
                {{ __('invoice_qr_enlarge') }}
            </span>
        </button>

        <div
            x-show="open"
            x-cloak
            x-transition.opacity
            class="fixed inset-0 z-[240] flex items-center justify-center bg-slate-950/70 p-4"
            role="dialog"
            aria-modal="true"
            aria-label="{{ __('invoice_pdf_scan_to_pay') }}"
            @click.self="open = false"
        >
            <div class="w-full max-w-xs rounded-2xl bg-white p-5 text-center shadow-2xl dark:bg-slate-900">
                <img
                    src="{{ $qrSrc }}"
                    alt="{{ __('invoice_pdf_scan_to_pay') }}"
                    class="mx-auto h-64 w-64 rounded-xl bg-white p-2"
                />
                <p class="mt-3 text-sm font-semibold text-slate-800 dark:text-slate-100">{{ __('invoice_pdf_scan_to_pay') }}</p>
                @if ($qrUrl)
                    <a
                        href="{{ $qrUrl }}"
                        class="mt-2 inline-block break-all text-[11px] text-indigo-600 hover:text-indigo-500 dark:text-indigo-400"
                        target="_blank"
                        rel="noopener noreferrer"
                    >{{ $qrUrl }}</a>
                @endif
                <button
                    type="button"
                    class="mt-4 inline-flex items-center justify-center rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800"
                    @click="open = false"
                >
                    {{ __('Close') }}
                </button>
            </div>
        </div>
    </div>
@endif
