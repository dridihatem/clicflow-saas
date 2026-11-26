@props([
    'label',
    'active' => false,
    'items' => [],
])

@php
    $items = is_array($items) ? array_values($items) : [];
@endphp

@if (count($items) === 1)
    <x-nav-link :href="$items[0]['url']" :active="$items[0]['active'] ?? false">
        {{ $items[0]['label'] }}
    </x-nav-link>
@elseif (count($items) > 1)
    <x-dropdown
        align="left"
        width="56"
        wrapper-class="relative z-[120] inline-flex"
        floating-class="z-[130]"
        content-classes="py-1 overflow-hidden bg-white/95 backdrop-blur-md dark:bg-slate-800/95 dark:ring-1 dark:ring-slate-600/80"
    >
        <x-slot name="trigger">
            <button
                type="button"
                @class([
                    'inline-flex items-center gap-1 rounded-lg px-3 py-2 text-sm font-medium transition duration-150 ease-in-out',
                    'bg-slate-100 text-slate-900 dark:bg-slate-800/80 dark:text-white' => $active,
                    'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800/80 dark:hover:text-white' => ! $active,
                ])
                aria-haspopup="true"
            >
                <span>{{ $label }}</span>
                <i class="fa-solid fa-chevron-down text-[10px] text-slate-400" aria-hidden="true"></i>
            </button>
        </x-slot>
        <x-slot name="content">
            <div class="border-b border-slate-100 px-3 py-2 dark:border-slate-700/80">
                <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $label }}</p>
            </div>
            <div class="max-h-[min(60vh,20rem)] overflow-y-auto overscroll-contain py-1 [scrollbar-width:thin]">
                @foreach ($items as $item)
                    <x-dropdown-link
                        :href="$item['url']"
                        @class([
                            'dark:text-slate-200 dark:hover:bg-slate-700/80',
                            'bg-indigo-50 font-semibold text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-200' => $item['active'] ?? false,
                        ])
                    >
                        {{ $item['label'] }}
                    </x-dropdown-link>
                @endforeach
            </div>
        </x-slot>
    </x-dropdown>
@endif
