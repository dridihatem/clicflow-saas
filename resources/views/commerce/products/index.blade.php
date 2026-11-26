<x-app-layout>
    <x-slot name="header">
        <h2 class="text-lg font-semibold tracking-tight text-slate-900 dark:text-white">{{ __('Products & services') }}</h2>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-12xl space-y-6 px-4 sm:px-6 lg:px-8">
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"
                @if ($flowdeskPlanGates['ai_credits'] ?? false)
                    x-data="{ open: @js($errors->has('brief') || filled(old('brief'))) }"
                    x-on:keydown.escape.window="open = false"
                @endif
            >
                <x-flow.page-header
                    :title="__('Products & services')"
                    :description="__('commerce_products_intro')"
                />
                <div class="flex flex-wrap items-center justify-end gap-2">
                    @if ($flowdeskPlanGates['ai_credits'] ?? false)
                        @php
                            $aiCatalogCost = (int) ($flowdeskAiTaskCredits['product_catalog'] ?? 150);
                        @endphp
                        <button
                            type="button"
                            class="inline-flex items-center gap-2 rounded-lg border border-violet-200 bg-violet-50 px-4 py-2.5 text-sm font-semibold text-violet-800 shadow-sm transition hover:border-violet-300 hover:bg-violet-100 dark:border-violet-500/40 dark:bg-violet-950/50 dark:text-violet-100 dark:hover:bg-violet-900/40"
                            @click="open = true"
                        >
                            <i class="fa-solid fa-wand-magic-sparkles text-sm" aria-hidden="true"></i>
                            {{ __('catalog_ai_generate') }} ({{ $aiCatalogCost }} {{ __('credits') }})
                        </button>
                    @endif
                    <a href="{{ route('commerce.products.create') }}">
                        <x-primary-button type="button" class="!normal-case">{{ __('commerce_add_item') }}</x-primary-button>
                    </a>
                </div>

                @if ($flowdeskPlanGates['ai_credits'] ?? false)
                    <div
                        x-show="open"
                        x-cloak
                        x-transition.opacity
                        class="fixed inset-0 z-[220] flex items-end justify-center bg-slate-950/60 p-4 sm:items-center"
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby="catalog_ai_bulk_title"
                        @click.self="open = false"
                    >
                        <div class="w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-5 shadow-2xl dark:border-slate-600 dark:bg-slate-900 sm:p-6">
                            <div class="flex items-start gap-3">
                                <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-violet-100 text-violet-700 dark:bg-violet-950/70 dark:text-violet-200">
                                    <i class="fa-solid fa-wand-magic-sparkles text-sm" aria-hidden="true"></i>
                                </span>
                                <div>
                                    <h3 id="catalog_ai_bulk_title" class="text-base font-semibold text-slate-900 dark:text-white">{{ __('catalog_ai_bulk_heading') }}</h3>
                                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ __('catalog_ai_bulk_intro') }}</p>
                                </div>
                            </div>

                            <form method="POST" action="{{ route('commerce.products.ai-catalog') }}" class="mt-4 space-y-3">
                                @csrf
                                <x-input-label for="catalog_ai_bulk_brief" :value="__('catalog_ai_bulk_prompt')" />
                                <x-ai-voice-wrap target-id="catalog_ai_bulk_brief" submit-button-id="catalog_ai_bulk_submit" class="mt-1">
                                    <textarea
                                        id="catalog_ai_bulk_brief"
                                        name="brief"
                                        rows="5"
                                        required
                                        minlength="8"
                                        class="block w-full rounded-lg border-slate-300 shadow-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"
                                        placeholder="{{ __('catalog_ai_bulk_placeholder') }}"
                                    >{{ old('brief') }}</textarea>
                                </x-ai-voice-wrap>
                                <x-input-error :messages="$errors->get('brief')" class="mt-1" />
                                <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('AI-generated content — review before sending to clients.') }}</p>
                                <div class="flex flex-wrap items-center justify-end gap-2 pt-1">
                                    <button
                                        type="button"
                                        class="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800"
                                        @click="open = false"
                                    >
                                        {{ __('Cancel') }}
                                    </button>
                                    <x-primary-button type="submit" id="catalog_ai_bulk_submit" class="!normal-case">
                                        {{ __('catalog_ai_bulk_generate') }} ({{ $aiCatalogCost }} {{ __('credits') }})
                                    </x-primary-button>
                                </div>
                            </form>
                        </div>
                    </div>
                @endif
            </div>

            @if (session('status'))
                <div class="flex items-start gap-2 rounded-xl border border-emerald-200/80 bg-emerald-50/90 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900/40 dark:bg-emerald-950/50 dark:text-emerald-100"><x-cf-icon name="check-circle" size="sm" />{{ session('status') }}</div>
            @endif
            @if (session('error'))
                <div class="flex items-start gap-2 rounded-xl border border-amber-200/80 bg-amber-50/90 px-4 py-3 text-sm text-amber-900 dark:border-amber-900/40 dark:bg-amber-950/50 dark:text-amber-100"><x-cf-icon name="info" size="sm" />{{ session('error') }}</div>
            @endif

            <form method="GET" action="{{ route('commerce.products.index') }}" class="flow-panel flex flex-wrap items-end gap-3 p-4 sm:p-5">
                <div>
                    <x-input-label for="kind" :value="__('Type')" />
                    <select id="kind" name="kind" class="flow-input-select mt-1 block min-w-[12rem]">
                        <option value="">{{ __('All') }}</option>
                        <option value="product" @selected(($kind ?? '') === 'product')>{{ __('catalog_kind_product') }}</option>
                        <option value="service" @selected(($kind ?? '') === 'service')>{{ __('catalog_kind_service') }}</option>
                    </select>
                </div>
                <x-secondary-button type="submit" class="!normal-case">{{ __('Filter') }}</x-secondary-button>
                @if (($kind ?? '') !== '')
                    <a href="{{ route('commerce.products.index') }}" class="text-sm font-medium text-indigo-600 hover:underline dark:text-indigo-400">{{ __('Clear') }}</a>
                @endif
            </form>

            <div
                x-data="{
                    pageIds: @js($products->getCollection()->pluck('id')->map(fn ($id) => (string) $id)->values()),
                    total: @js($products->total()),
                    selected: [],
                    allMatching: false,
                    confirmOpen: false,
                    get allOnPage() { return this.pageIds.length > 0 && this.pageIds.every(id => this.selected.includes(id)); },
                    get count() { return this.allMatching ? this.total : this.selected.length; },
                    toggleAll(e) { this.allMatching = false; this.selected = e.target.checked ? [...this.pageIds] : []; },
                    clear() { this.selected = []; this.allMatching = false; },
                }"
                x-on:keydown.escape.window="confirmOpen = false"
                class="space-y-3"
            >
            <form id="products-bulk-delete-form" method="POST" action="{{ route('commerce.products.bulk-destroy') }}" class="hidden">
                @csrf
                @method('DELETE')
                <input type="hidden" name="kind" value="{{ $kind ?? '' }}">
                <template x-if="allMatching"><input type="hidden" name="all_matching" value="1"></template>
                <template x-if="!allMatching">
                    <div><template x-for="id in selected" :key="id"><input type="hidden" name="ids[]" :value="id"></template></div>
                </template>
            </form>

            <div
                x-show="count > 0"
                x-cloak
                x-transition.opacity
                class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-orange-200 bg-orange-50/90 px-4 py-3 text-sm shadow-sm dark:border-orange-500/30 dark:bg-orange-950/40"
                data-testid="products-bulk-bar"
            >
                <div class="flex flex-wrap items-center gap-3 text-orange-900 dark:text-orange-100">
                    <x-cf-icon name="check-circle" size="sm" />
                    <span class="font-semibold" x-text="count === 1 ? @js(__('commerce_bulk_selected_one')) : @js(__('commerce_bulk_selected_many')).replace(':count', count)"></span>
                    @if ($products->total() > $products->count())
                        <button type="button" x-show="allOnPage && !allMatching" class="font-medium text-orange-700 underline-offset-2 hover:underline dark:text-orange-300" @click="allMatching = true">
                            {{ __('commerce_bulk_select_all_matching', ['count' => $products->total()]) }}
                        </button>
                    @endif
                    <button type="button" class="font-medium text-slate-600 hover:underline dark:text-slate-300" @click="clear()">{{ __('commerce_bulk_clear') }}</button>
                </div>
                <button type="button" @click="confirmOpen = true" class="inline-flex items-center gap-2 rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-rose-700 focus:outline-none focus:ring-2 focus:ring-rose-400 focus:ring-offset-2">
                    <x-cf-icon name="trash" size="xs" />
                    {{ __('commerce_bulk_delete_button') }}
                </button>
            </div>

            <template x-teleport="body">
            <div
                x-show="confirmOpen"
                x-cloak
                x-transition.opacity
                class="fixed inset-0 z-[230] flex items-end justify-center bg-slate-950/60 p-4 sm:items-center"
                role="dialog"
                aria-modal="true"
                aria-labelledby="products_bulk_confirm_title"
                @click.self="confirmOpen = false"
            >
                <div class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-5 shadow-2xl dark:border-slate-600 dark:bg-slate-900 sm:p-6">
                    <div class="flex items-start gap-3">
                        <x-cf-icon name="trash" variant="tile" tone="rose" size="md" />
                        <div>
                            <h3 id="products_bulk_confirm_title" class="text-base font-semibold text-slate-900 dark:text-white"
                                x-text="count === 1 ? @js(__('commerce_bulk_confirm_title_one')) : @js(__('commerce_bulk_confirm_title_many')).replace(':count', count)"></h3>
                            <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">{{ __('commerce_bulk_confirm_body') }}</p>
                            <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">{{ __('commerce_bulk_confirm_skip_hint') }}</p>
                        </div>
                    </div>
                    <div class="mt-5 flex flex-wrap items-center justify-end gap-2">
                        <button type="button" class="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800" @click="confirmOpen = false">{{ __('Cancel') }}</button>
                        <button type="submit" form="products-bulk-delete-form" class="inline-flex items-center gap-2 rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-rose-700">
                            <x-cf-icon name="trash" size="xs" />
                            {{ __('commerce_bulk_confirm_submit') }}
                        </button>
                    </div>
                </div>
            </div>
            </template>

            <div class="flow-panel overflow-hidden p-0">
                <x-flow.table>
                    <thead class="bg-slate-50/90 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/80 dark:text-slate-400">
                        <tr>
                            <th class="w-10 px-4 py-3 text-start">
                                <input type="checkbox" class="rounded border-slate-300 text-orange-600 focus:ring-orange-500 dark:border-slate-600 dark:bg-slate-800"
                                    :checked="allOnPage" @change="toggleAll($event)" :disabled="pageIds.length === 0"
                                    aria-label="{{ __('commerce_bulk_select_page') }}" title="{{ __('commerce_bulk_select_page') }}">
                            </th>
                            <th class="px-4 py-3 text-start">{{ __('Name') }}</th>
                            <th class="px-4 py-3 text-start">{{ __('Type') }}</th>
                            <th class="px-4 py-3 text-start">{{ __('SKU') }}</th>
                            <th class="px-4 py-3 text-start">{{ __('Price') }}</th>
                            <th class="px-4 py-3 text-start">{{ __('product_tax_column') }}</th>
                            <th class="px-4 py-3 text-start">{{ __('Variants') }}</th>
                            <th class="px-4 py-3 text-end">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200/80 dark:divide-slate-700/80">
                        @forelse ($products as $product)
                            @php
                                $isService = $product->isService();
                            @endphp
                            <tr class="transition hover:bg-slate-50/80 dark:hover:bg-slate-800/40" :class="selected.includes(@js((string) $product->id)) || allMatching ? 'bg-orange-50/70 dark:bg-orange-950/30' : ''">
                                <td class="px-4 py-3">
                                    <input type="checkbox" value="{{ $product->id }}" x-model="selected" @change="allMatching = false"
                                        class="rounded border-slate-300 text-orange-600 focus:ring-orange-500 dark:border-slate-600 dark:bg-slate-800"
                                        aria-label="{{ __('commerce_bulk_select_item', ['name' => $product->name]) }}">
                                </td>
                                <td class="px-4 py-3 font-medium text-slate-900 dark:text-white">{{ $product->name }}</td>
                                <td class="px-4 py-3">
                                    <span @class([
                                        'inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold',
                                        'bg-cyan-500/15 text-cyan-900 dark:text-cyan-100' => $isService,
                                        'bg-indigo-500/15 text-indigo-900 dark:text-indigo-100' => ! $isService,
                                    ])>
                                        {{ $product->kind?->label() ?? __('catalog_kind_product') }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 font-mono text-sm text-slate-600 dark:text-slate-300">{{ $product->sku ?: '—' }}</td>
                                <td class="px-4 py-3 tabular-nums text-slate-800 dark:text-slate-100">
                                    {{ flowdesk_format_minor($product->base_price_minor, $product->currency) }} {{ $product->currency }}
                                </td>
                                <td class="px-4 py-3 tabular-nums text-slate-700 dark:text-slate-200">
                                    @if ($product->taxLabel() !== '')
                                        <span class="inline-flex rounded-full bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-800 dark:bg-amber-500/10 dark:text-amber-300">{{ $product->taxLabel() }}</span>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $product->variants->count() }}</td>
                                <td class="px-4 py-3 text-end">
                                    <a
                                        href="{{ route('commerce.products.show', $product) }}"
                                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200/80 bg-white text-slate-600 shadow-sm transition hover:border-indigo-200 hover:text-indigo-600 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300 dark:hover:border-indigo-500/40 dark:hover:text-indigo-400"
                                        title="{{ __('View') }}"
                                        aria-label="{{ __('View') }}"
                                    >
                                        <i class="fa-regular fa-eye text-sm" aria-hidden="true"></i>
                                    </a>
                                    <button type="button"
                                        class="ms-1 inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200/80 bg-white text-slate-500 shadow-sm transition hover:border-rose-200 hover:text-rose-600 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300 dark:hover:border-rose-500/40 dark:hover:text-rose-400"
                                        title="{{ __('Delete') }}" aria-label="{{ __('Delete') }}"
                                        @click="allMatching = false; selected = [@js((string) $product->id)]; confirmOpen = true">
                                        <x-cf-icon name="trash" size="xs" />
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-10 text-center text-sm text-slate-500 dark:text-slate-400">{{ __('commerce_products_empty') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </x-flow.table>
            </div>

            </div>

            @if ($products->hasPages())
                <div>{{ $products->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
