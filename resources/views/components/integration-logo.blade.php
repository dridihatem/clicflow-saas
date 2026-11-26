@props([
    'name' => '',
    'size' => 'md',
])

@php
    $name = strtolower(trim((string) $name));
    $box = match ($size) {
        'sm' => 'h-8 w-8',
        'lg' => 'h-14 w-14',
        default => 'h-11 w-11',
    };
    $labels = [
        'perfex' => 'Perfex',
        'shopify' => 'Shopify',
        'woocommerce' => 'WooCommerce',
        'odoo' => 'Odoo',
        'quickbooks' => 'QuickBooks',
        'trello' => 'Trello',
        'notion' => 'Notion',
    ];
    $label = $labels[$name] ?? ucfirst($name);
@endphp

<span {{ $attributes->class(['inline-flex shrink-0 items-center justify-center overflow-hidden rounded-xl shadow-sm ring-1 ring-black/5 dark:ring-white/10', $box]) }} data-integration-logo="{{ $name }}" title="{{ $label }}">
    @switch($name)
        @case('shopify')
            <svg viewBox="0 0 48 48" class="h-full w-full" aria-hidden="true">
                <rect width="48" height="48" rx="12" fill="#95BF47"/>
                <path fill="#fff" d="M15.4 20.1h17.2l-1.35 14.6c-.18 1.9-1.7 3.3-3.6 3.3h-7.3c-1.9 0-3.42-1.4-3.6-3.3L15.4 20.1Z"/>
                <path fill="none" stroke="#fff" stroke-width="2.3" stroke-linecap="round" d="M20.2 20c.15-4.1 1.85-6.8 3.8-6.8 1.95 0 3.65 2.7 3.8 6.8"/>
                <path fill="#95BF47" d="M24.15 26.05c1.35-.38 2.05-.95 2.05-1.85 0-1.1-.95-1.7-2.45-1.7-1.15 0-2.15.35-2.95.75l.5 1.85c.6-.35 1.35-.65 2.1-.65.5 0 .8.18.8.5 0 .38-.4.58-1.4.86-1.5.42-2.25 1.1-2.25 2.15 0 1.28 1.1 2 2.65 2 1.15 0 2.2-.38 3-.95l-.55-1.9c-.65.4-1.45.7-2.2.7-.55 0-.85-.18-.85-.5 0-.28.3-.48 1.55-.86Z"/>
            </svg>
            @break
        @case('woocommerce')
            <svg viewBox="0 0 48 48" class="h-full w-full" aria-hidden="true">
                <rect width="48" height="48" rx="12" fill="#7F54B3"/>
                <path fill="#fff" d="M12.8 16.2h4.05l.55 2.15h16.1a1.4 1.4 0 0 1 1.35 1.75l-2.15 7.2a1.8 1.8 0 0 1-1.75 1.3H19.1l-.55 1.85h12.7v2.35H16.4a1.5 1.5 0 0 1-1.45-1.9l2.35-8.35-.7-2.7h-3.8V16.2Z"/>
                <circle cx="21.1" cy="34.4" r="2.15" fill="#fff"/>
                <circle cx="30.4" cy="34.4" r="2.15" fill="#fff"/>
            </svg>
            @break
        @case('odoo')
            <svg viewBox="0 0 48 48" class="h-full w-full" aria-hidden="true">
                <rect width="48" height="48" rx="12" fill="#714B67"/>
                <circle cx="23.2" cy="25" r="10.2" fill="none" stroke="#fff" stroke-width="3.6"/>
                <circle cx="34.2" cy="13.8" r="2.55" fill="#fff"/>
            </svg>
            @break
        @case('trello')
            <svg viewBox="0 0 48 48" class="h-full w-full" aria-hidden="true">
                <rect width="48" height="48" rx="12" fill="#0079BF"/>
                <rect x="11.5" y="11.5" width="11" height="24" rx="2.8" fill="#fff"/>
                <rect x="25.5" y="11.5" width="11" height="15" rx="2.8" fill="#fff"/>
            </svg>
            @break
        @case('notion')
            <svg viewBox="0 0 48 48" class="h-full w-full" aria-hidden="true">
                <rect width="48" height="48" rx="12" class="fill-[#191919] dark:fill-white"/>
                <path class="fill-white dark:fill-[#191919]" d="M16.4 13.2h15.4c.9 0 1.4.4 1.7 1.1l1.3 3.1c.15.35 0 .6-.4.62l-1.15.12v12.3c0 1.15-.55 1.76-1.7 1.84l-13.4.7c-.85.05-1.25-.2-1.7-.85l-2.7-3.5c-.45-.6-.65-1.05-.65-1.6V14.7c0-.9.5-1.5 1.55-1.5Zm2.35 4.15-.15 13.05 1.85.2v-7.55l4.85 7.35 2.15-.1V17.7l-1.85-.15v9.05l-4.7-7.2-2.15-.13Zm9.85.05 1.85.15v11.7l-1.85.2V17.4Z"/>
            </svg>
            @break
        @case('quickbooks')
            <svg viewBox="0 0 48 48" class="h-full w-full" aria-hidden="true">
                <rect width="48" height="48" rx="12" fill="#2CA01C"/>
                <circle cx="23" cy="24" r="10.2" fill="none" stroke="#fff" stroke-width="3.6"/>
                <path fill="none" stroke="#fff" stroke-width="3.6" stroke-linecap="round" d="M30.2 31.2 35.4 36.4"/>
            </svg>
            @break
        @case('perfex')
            <svg viewBox="0 0 48 48" class="h-full w-full" aria-hidden="true">
                <rect width="48" height="48" rx="12" fill="#16A34A"/>
                <path fill="#fff" d="M24 8.6 36.2 15.6v16.8L24 39.4 11.8 32.4V15.6L24 8.6Z"/>
                <path fill="#16A34A" d="M20.2 18.1h5.4c2.55 0 4.15 1.45 4.15 3.7 0 2.25-1.65 3.75-4.25 3.75h-2.3v4.35h-3V18.1Zm3 5.35h2.05c1.1 0 1.75-.6 1.75-1.6s-.65-1.6-1.75-1.6H23.2v3.2Z"/>
            </svg>
            @break
        @default
            <svg viewBox="0 0 48 48" class="h-full w-full" aria-hidden="true">
                <rect width="48" height="48" rx="12" class="fill-slate-200 dark:fill-slate-700"/>
                <path class="fill-slate-500 dark:fill-slate-300" d="M16 24a8 8 0 1 1 16 0 8 8 0 0 1-16 0Zm8-4.5a4.5 4.5 0 1 0 0 9 4.5 4.5 0 0 0 0-9Z"/>
            </svg>
    @endswitch
    <span class="sr-only">{{ $label }}</span>
</span>
