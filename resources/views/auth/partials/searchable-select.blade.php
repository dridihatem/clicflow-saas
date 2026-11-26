@props([
    'options' => [],
    'name',
    'id' => null,
    'value' => null,
    'placeholder' => null,
    'required' => false,
    'searchPlaceholder' => null,
    'emptyLabel' => null,
    'selectClass' => 'flow-input-select',
    'autocomplete' => null,
])

@php
    $id = $id ?? $name;
    $selected = old($name, $value);
    $placeholder = $placeholder ?? __('— Select —');
    $searchPlaceholder = $searchPlaceholder ?? __('Search country…');
    $emptyLabel = $emptyLabel ?? __('No countries found');
    $normalized = [];
    foreach ($options as $opt) {
        $normalized[] = [
            'value' => (string) ($opt['value'] ?? ''),
            'label' => (string) ($opt['label'] ?? ''),
            'search' => strtolower((string) ($opt['search'] ?? ($opt['label'] ?? ''))),
        ];
    }
@endphp

<div
    class="relative mt-2"
    x-data="{
        open: false,
        query: '',
        value: @js((string) ($selected ?? '')),
        options: @js($normalized),
        get filtered() {
            const q = this.query.trim().toLowerCase();
            if (! q) return this.options;
            return this.options.filter((o) =>
                o.search.includes(q) ||
                o.label.toLowerCase().includes(q) ||
                o.value.toLowerCase().includes(q)
            );
        },
        get selectedLabel() {
            if (! this.value) return '';
            const hit = this.options.find((o) => o.value === this.value);
            return hit ? hit.label : '';
        },
        select(v) {
            this.value = v;
            this.open = false;
            this.query = '';
            this.$nextTick(() => {
                const native = this.$refs.native;
                if (! native) return;
                native.value = v;
                native.dispatchEvent(new Event('change', { bubbles: true }));
            });
        },
        syncFromNative() {
            const native = this.$refs.native;
            if (! native) return;
            const next = native.value || '';
            if (this.value !== next) {
                this.value = next;
            }
        },
        init() {
            this.$nextTick(() => {
                const native = this.$refs.native;
                if (! native) return;
                this.syncFromNative();
                native.addEventListener('change', () => this.syncFromNative());
            });
        },
        toggle() {
            this.open = ! this.open;
            if (this.open) {
                this.$nextTick(() => this.$refs.search?.focus());
            }
        },
        close() {
            this.open = false;
            this.query = '';
        },
    }"
    @keydown.escape.window="close()"
    @click.outside="close()"
>
    <select
        x-ref="native"
        id="{{ $id }}"
        name="{{ $name }}"
        @if ($required) required @endif
        @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
        tabindex="-1"
        aria-hidden="true"
        class="pointer-events-none absolute h-px w-px opacity-0"
    >
        <option value="">{{ $placeholder }}</option>
        @foreach ($normalized as $opt)
            <option value="{{ $opt['value'] }}" @selected((string) $selected === (string) $opt['value'])>{{ $opt['label'] }}</option>
        @endforeach
    </select>

    <button
        type="button"
        class="{{ $selectClass }} flex w-full items-center justify-between gap-2 text-start"
        @click="toggle()"
        :aria-expanded="open.toString()"
        aria-haspopup="listbox"
        aria-controls="{{ $id }}_listbox"
    >
        <span class="min-w-0 truncate" :class="value ? 'text-slate-800' : 'text-slate-400'" x-text="selectedLabel || @js($placeholder)"></span>
        <svg class="h-4 w-4 shrink-0 text-slate-400 transition" :class="open ? 'rotate-180' : ''" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
        </svg>
    </button>

    <div
        x-show="open"
        x-cloak
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 translate-y-1"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="absolute inset-x-0 z-50 mt-1.5 overflow-hidden rounded-xl border border-[rgba(91,100,140,0.16)] bg-white shadow-[0_16px_40px_-16px_rgba(15,23,42,0.35)]"
        id="{{ $id }}_listbox"
        role="listbox"
    >
        <div class="border-b border-[rgba(91,100,140,0.1)] p-2">
            <input
                x-ref="search"
                type="search"
                x-model="query"
                placeholder="{{ $searchPlaceholder }}"
                class="flow-input block w-full !py-2 text-sm"
                autocomplete="off"
                @keydown.enter.prevent="if (filtered.length) select(filtered[0].value)"
            />
        </div>
        <ul class="max-h-52 overflow-y-auto py-1">
            <li class="px-3 py-2 text-sm text-slate-400" x-show="filtered.length === 0" x-cloak>{{ $emptyLabel }}</li>
            <template x-for="opt in filtered" :key="opt.value || 'empty'">
                <li>
                    <button
                        type="button"
                        class="flex w-full items-center px-3 py-2 text-start text-sm text-slate-700 transition hover:bg-[rgba(234,88,12,0.08)] hover:text-[#C2410C]"
                        :class="opt.value === value ? 'bg-[rgba(234,88,12,0.1)] font-semibold text-[#C2410C]' : ''"
                        role="option"
                        :aria-selected="(opt.value === value).toString()"
                        @click="select(opt.value)"
                        x-text="opt.label"
                    ></button>
                </li>
            </template>
        </ul>
    </div>
</div>
