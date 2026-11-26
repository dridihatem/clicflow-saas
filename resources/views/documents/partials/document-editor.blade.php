{{--
    Shared invoice / quote editor (one component so both forms stay consistent).
    Required: $kind ('invoice'|'quote'), $formAction, $xData (Alpine invoiceForm/quoteForm call), $clients,
    $currencyOptions, $cancelUrl, $previewUrl. Optional: $doc, $isEdit, $selectedClientId, $referencePreview,
    invoice: $invoiceProjectPrefill, $clientModeDefault; quote: $projects, $nameValue, $customerNotesDefault,
    $paymentModalities, $selectedTerms, $docCurrency.
--}}
@php
    $isQuote = $kind === 'quote';
    $doc = $doc ?? null;
    $isEdit = (bool) ($isEdit ?? false);
    $user = auth()->user();
    $company = $user?->company;
    $canQuickProduct = $user && ($user->can('workspace.manage_commerce') || $user->can('workspace.manage_invoices'));
    $currencyValue = old('currency', $doc?->currency ?? $company?->default_currency ?? flowdesk_default_currency());
    $selectedClientId = (string) old('client_id', $doc?->client_id ?? ($selectedClientId ?? ''));
    $customerNotes = old('customer_notes', $doc?->customer_notes ?? ($customerNotesDefault ?? ''));
    $internalNotes = old('internal_notes', $doc?->internal_notes ?? '');
    $advancedOpen = $errors->hasAny(['customer_notes', 'internal_notes', 'currency', 'advance_percent', 'advance_amount_major', 'payment_terms', 'recurrence_interval', 'recurrence_every_months', 'recurrence_ends_on'])
        || filled($customerNotes) || filled($internalNotes)
        || (! $isQuote && (bool) old('recurrence_active', $doc?->recurrence_active ?? false))
        || ($isQuote && (filled(old('advance_percent', $doc?->advance_percent)) || ! empty($selectedTerms ?? [])));
    $previewLabel = __('doc_editor_preview_pdf');
    $inputCls = 'block w-full rounded-lg border-slate-300 bg-white text-sm shadow-sm focus:border-[var(--flow-primary)] focus:ring-[var(--flow-primary)] dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100';
    $cellCls = 'block w-full rounded-md border-slate-200 bg-white px-2 py-1.5 text-sm shadow-none focus:border-[var(--flow-primary)] focus:ring-[var(--flow-primary)] dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100';
    $labelCls = 'block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400';
@endphp

<form
    method="POST"
    action="{{ $formAction }}"
    x-data="{!! $xData !!}"
    class="cf-doc-editor"
    data-cf-doc-editor="{{ $kind }}"
    data-client-mode="{{ old('client_mode', $clientModeDefault ?? 'pick') }}"
    data-quick-error="{{ __('Could not save client.') }}"
    x-effect="document.documentElement.classList.toggle('cf-doc-aside-open', !!(qpOpen || quickOpen))"
>
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif
    @if (! $isQuote)
        @if (! empty($invoiceProjectPrefill['project_id'] ?? null))
            <input type="hidden" name="project_id" value="{{ $invoiceProjectPrefill['project_id'] }}" />
        @endif
        @unless ($isEdit)
            <input type="hidden" name="client_mode" :value="clientMode" />
        @endunless
    @endif

    <div class="mx-auto max-w-12xl">
        <div class="min-w-0 space-y-6">
            {{-- 1. Header: client, dates, number --}}
            <section class="rounded-2xl border border-slate-200/90 bg-white p-5 shadow-sm dark:border-slate-700/80 dark:bg-slate-900/40 sm:p-6" data-doc-editor-header>
                @if ($isQuote)
                    <div class="mb-4">
                        <label for="name" class="{{ $labelCls }}">{{ __('Quote title') }} *</label>
                        <input id="name" name="name" type="text" required autofocus maxlength="255" value="{{ $nameValue ?? '' }}"
                            class="mt-1 {{ $inputCls }} text-base font-medium" placeholder="{{ __('doc_editor_quote_title_ph') }}" />
                        <x-input-error class="mt-1" :messages="$errors->get('name')" />
                    </div>
                @endif

                <div class="grid gap-4 md:grid-cols-12">
                    {{-- Client (searchable) --}}
                    <div class="md:col-span-6" x-data="cfClientPicker()">
                        <div class="flex items-center justify-between gap-2">
                            <span class="{{ $labelCls }}">{{ __('Client') }}@if ($isQuote) *@endif</span>
                            @if (! $isQuote && ! $isEdit)
                                <button type="button" class="text-xs font-medium text-slate-500 hover:text-[var(--flow-primary)]" x-show="clientMode === 'pick'" @click="clientMode = 'new'">{{ __('Create new on save') }}</button>
                                <button type="button" class="text-xs font-medium text-slate-500 hover:text-[var(--flow-primary)]" x-show="clientMode === 'new'" x-cloak @click="clientMode = 'pick'">{{ __('Select existing') }}</button>
                            @endif
                        </div>
                        <div x-show="clientMode === 'pick'" class="relative mt-1" @click.outside="open = false" @keydown.escape="open = false">
                            <div class="flex items-stretch gap-2">
                                <div class="relative min-w-0 flex-1">
                                    <span class="pointer-events-none absolute inset-y-0 start-2.5 flex items-center text-slate-400"><x-cf-icon name="magnifying-glass" size="sm" /></span>
                                    <input type="text" autocomplete="off" data-client-search
                                        class="{{ $inputCls }} ps-9"
                                        :placeholder="label || @js(__('doc_editor_client_search_ph'))"
                                        :class="label && !open ? 'placeholder:text-slate-900 dark:placeholder:text-slate-100 placeholder:font-medium' : ''"
                                        x-model="q"
                                        @focus="open = true; active = 0"
                                        @input="open = true; active = 0"
                                        @keydown.arrow-down.prevent="active = Math.min(active + 1, filtered.length - 1)"
                                        @keydown.arrow-up.prevent="active = Math.max(active - 1, 0)"
                                        @keydown.enter.prevent="filtered[active] && pick(filtered[active])" />
                                    <button type="button" class="absolute inset-y-0 end-2 flex items-center text-slate-400 hover:text-rose-500" x-show="label" x-cloak @click="pick(null)" title="{{ __('doc_editor_client_clear') }}"><x-cf-icon name="xmark" size="sm" /></button>
                                </div>
                                @if (! empty($clientsStoreAvailable))
                                    <button type="button" class="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-[var(--flow-primary)]/40 px-3 text-sm font-medium text-[var(--flow-primary)] hover:bg-orange-50 dark:hover:bg-orange-950/30"
                                        @click="openQuickClientAside()" title="{{ __('Add client to list now') }}" data-quick-client-open>
                                        <x-cf-icon name="user-plus" size="sm" /><span class="hidden sm:inline">{{ __('doc_editor_new_client') }}</span>
                                    </button>
                                @endif
                            </div>
                            <div x-show="open" x-cloak class="absolute z-30 mt-1 max-h-64 w-full overflow-y-auto rounded-xl border border-slate-200 bg-white py-1 shadow-lg dark:border-slate-700 dark:bg-slate-900">
                                <template x-for="(o, k) in filtered" :key="o.id">
                                    <button type="button" class="flex w-full flex-col px-3 py-2 text-start text-sm hover:bg-orange-50 dark:hover:bg-slate-800"
                                        :class="k === active ? 'bg-orange-50 dark:bg-slate-800' : ''" @mouseenter="active = k" @click="pick(o)">
                                        <span class="font-medium text-slate-900 dark:text-slate-100" x-text="o.name"></span>
                                        <span class="text-xs text-slate-500" x-show="o.email" x-text="o.email"></span>
                                    </button>
                                </template>
                                <p class="px-3 py-2 text-sm text-slate-500" x-show="!filtered.length">{{ __('doc_editor_client_none') }}</p>
                            </div>
                            <select id="client_id" name="client_id" class="sr-only" tabindex="-1" @if ($isQuote) required @endif>
                                <option value="">{{ $isQuote ? __('Select client') : __('— Optional —') }}</option>
                                @foreach ($clients as $c)
                                    <option value="{{ $c->id }}" @selected($selectedClientId === (string) $c->id) data-phone="{{ $c->phone }}" data-email="{{ $c->email }}">{{ $c->name }}@if ($c->code) ({{ $c->code }})@endif</option>
                                @endforeach
                                <template x-for="c in extraClients" :key="c.id">
                                    <option :value="c.id" x-text="c.name" :selected="c.id === selectedQuickId" :data-email="c.email || ''"></option>
                                </template>
                            </select>
                            <x-input-error class="mt-1" :messages="$errors->get('client_id')" />
                        </div>
                        @if (! $isQuote && ! $isEdit)
                            <div x-show="clientMode === 'new'" x-cloak class="mt-1 space-y-3 rounded-xl border border-dashed border-slate-300 p-3 dark:border-slate-600">
                                <input id="new_client_name" name="new_client_name" type="text" value="{{ old('new_client_name') }}" class="{{ $inputCls }}" placeholder="{{ __('New client name') }}" />
                                <x-input-error :messages="$errors->get('new_client_name')" />
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <input id="new_client_email" name="new_client_email" type="email" value="{{ old('new_client_email') }}" class="{{ $inputCls }}" placeholder="{{ __('Email') }}" />
                                    <input id="new_client_phone" name="new_client_phone" type="text" value="{{ old('new_client_phone') }}" class="{{ $inputCls }}" placeholder="{{ __('Phone') }}" />
                                </div>
                                <x-input-error :messages="array_merge($errors->get('new_client_email'), $errors->get('new_client_phone'))" />
                                <label class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200" for="create_client_account">
                                    <input type="hidden" name="create_client_account" value="0" />
                                    <input id="create_client_account" type="checkbox" name="create_client_account" value="1" class="rounded border-slate-300 text-[var(--flow-primary)] dark:border-slate-600" @checked(old('create_client_account')) />
                                    {{ __('Create login account for this client') }}
                                </label>
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <input id="new_client_password" name="new_client_password" type="password" autocomplete="new-password" class="{{ $inputCls }}" placeholder="{{ __('Password') }}" />
                                    <input id="new_client_password_confirmation" name="new_client_password_confirmation" type="password" autocomplete="new-password" class="{{ $inputCls }}" placeholder="{{ __('Confirm password') }}" />
                                </div>
                                <x-input-error :messages="$errors->get('new_client_password')" />
                            </div>
                        @endif
                    </div>

                    @if ($isQuote)
                        <div class="md:col-span-3">
                            <label for="valid_until" class="{{ $labelCls }}">{{ __('Valid until') }}</label>
                            <input id="valid_until" name="valid_until" type="date" value="{{ old('valid_until', $doc?->valid_until?->format('Y-m-d') ?? '') }}" class="mt-1 {{ $inputCls }}" />
                            <x-input-error class="mt-1" :messages="$errors->get('valid_until')" />
                        </div>
                        <div class="md:col-span-3">
                            <span class="{{ $labelCls }}">{{ __('doc_editor_number') }}</span>
                            <p class="mt-1 rounded-lg border border-dashed border-slate-300 px-3 py-2 font-mono text-sm text-slate-700 dark:border-slate-600 dark:text-slate-300">{{ $doc?->number ?: ($referencePreview ?: '—') }}</p>
                        </div>
                        <div class="md:col-span-6">
                            <label for="project_id" class="{{ $labelCls }}">{{ __('Project') }}</label>
                            <select id="project_id" name="project_id" class="mt-1 {{ $inputCls }}">
                                <option value="">{{ __('— None —') }}</option>
                                @foreach ($projects ?? [] as $p)
                                    <option value="{{ $p->id }}" @selected((string) old('project_id', $doc?->project_id ?? '') === (string) $p->id)>{{ $p->title }}</option>
                                @endforeach
                            </select>
                        </div>
                    @else
                        <div class="md:col-span-3">
                            <label for="due_date" class="{{ $labelCls }}">{{ __('Due date') }}</label>
                            <input id="due_date" name="due_date" type="date" value="{{ old('due_date', $doc?->due_date?->format('Y-m-d')) }}" class="mt-1 {{ $inputCls }}" />
                            <x-input-error class="mt-1" :messages="$errors->get('due_date')" />
                        </div>
                        <div class="md:col-span-3">
                            @if ($isEdit)
                                <label for="number" class="{{ $labelCls }}">{{ __('doc_editor_number') }}</label>
                                <input id="number" name="number" type="text" maxlength="64" value="{{ old('number', $doc?->number) }}" class="mt-1 {{ $inputCls }} font-mono" />
                                <x-input-error class="mt-1" :messages="$errors->get('number')" />
                            @else
                                <span class="{{ $labelCls }}">{{ __('doc_editor_number') }}</span>
                                <p class="mt-1 rounded-lg border border-dashed border-slate-300 px-3 py-2 text-slate-700 dark:border-slate-600 dark:text-slate-300 {{ $referencePreview ? 'font-mono text-sm' : 'text-xs leading-5' }}" title="{{ __('doc_editor_number_auto') }}">{{ $referencePreview ?? __('doc_editor_number_auto') }}</p>
                            @endif
                        </div>
                        <div class="md:col-span-6">
                            <label for="status" class="{{ $labelCls }}">{{ __('Status') }}</label>
                            <select id="status" name="status" required class="mt-1 {{ $inputCls }}">
                                @foreach (\App\Enums\InvoiceStatus::cases() as $case)
                                    <option value="{{ $case->value }}" @selected(old('status', $doc?->status?->value ?? 'draft') === $case->value)>{{ $case->label() }}</option>
                                @endforeach
                            </select>
                            <x-input-error class="mt-1" :messages="$errors->get('status')" />
                        </div>
                    @endif
                </div>
            </section>

            {{-- AI line generation (collapsed) --}}
            @if ($flowdeskPlanGates['ai_credits'] ?? true)
                <details class="group rounded-2xl border border-violet-200/80 bg-violet-50/40 dark:border-violet-900/40 dark:bg-violet-950/20" @if (! empty($aiPanelOpen)) open @endif>
                    <summary class="flex cursor-pointer list-none items-center gap-2 px-5 py-3 text-sm font-semibold text-violet-800 dark:text-violet-200">
                        <x-cf-icon name="wand-magic-sparkles" size="sm" />
                        {{ __('doc_editor_ai_toggle') }}
                        <span class="ms-auto text-violet-500 transition group-open:rotate-180"><x-cf-icon name="chevron-down" size="sm" /></span>
                    </summary>
                    <div class="px-2 pb-2">
                        @include($isQuote ? 'proposals.partials.line-items-ai-panel' : 'invoices.partials.line-items-ai-panel')
                    </div>
                </details>
            @endif

            {{-- 2. Lines --}}
            <section class="rounded-2xl border border-slate-200/90 bg-white shadow-sm dark:border-slate-700/80 dark:bg-slate-900/40" data-doc-editor-lines>
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200/80 px-5 py-3 dark:border-slate-700/80">
                    <h3 class="font-semibold text-slate-900 dark:text-white">{{ __('Line items') }}</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('doc_editor_keyboard_hint') }}</p>
                </div>

                <div class="hidden grid-cols-[20px_minmax(0,1fr)_72px_96px_120px_84px_118px_34px] gap-2 border-b border-slate-100 px-5 py-2 text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:border-slate-800 md:grid">
                    <span></span>
                    <span>{{ __('doc_editor_col_item') }}</span>
                    <span class="text-end">{{ __('Qty') }}</span>
                    <span>{{ __('doc_editor_col_unit') }}</span>
                    <span class="text-end">{{ __('Unit price (HT)') }}</span>
                    <span class="text-end">{{ __('doc_line_vat_col') }}</span>
                    <span class="text-end">{{ __('Line total (HT)') }}</span>
                    <span></span>
                </div>

                <div class="divide-y divide-slate-100 dark:divide-slate-800">
                    <template x-for="(line, i) in items" :key="line._k || i">
                        <div data-line-row
                            class="relative grid grid-cols-2 gap-2 px-4 py-3 transition md:grid-cols-[20px_minmax(0,1fr)_72px_96px_120px_84px_118px_34px] md:items-start md:px-5"
                            :class="dragOver === i && dragFrom !== null && dragFrom !== i ? 'bg-orange-50/70 dark:bg-orange-950/20' : ''"
                            @dragover.prevent="dragOver = i"
                            @dragleave="dragOver === i && (dragOver = null)"
                            @drop.prevent="onDrop(i)"
                            @keydown.alt.arrow-up.prevent="moveLine(i, i - 1); focusLine(i - 1)"
                            @keydown.alt.arrow-down.prevent="moveLine(i, i + 1); focusLine(i + 1)">
                            <button type="button" draggable="true" tabindex="-1"
                                class="hidden cursor-grab items-center justify-center pt-2 text-slate-400 hover:text-slate-700 active:cursor-grabbing md:flex"
                                @dragstart="onDragStart(i, $event)" @dragend="dragFrom = null; dragOver = null"
                                title="{{ __('doc_editor_drag') }}" data-drag-handle>
                                <x-cf-icon name="ellipsis-vertical" size="sm" />
                            </button>

                            <div class="col-span-2 min-w-0 space-y-1.5 md:col-span-1">
                                <div class="flex items-center gap-1.5">
                                    <span class="inline-flex h-6 min-w-6 items-center justify-center rounded-full bg-slate-100 px-1.5 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300 md:hidden" x-text="i + 1"></span>
                                    <select class="{{ $cellCls }} min-w-0 flex-1 text-slate-600"
                                        x-model="line.product_id" x-bind:name="'items['+i+'][product_id]'" @change="applyCatalog(line)"
                                        aria-label="{{ __('Product / service') }}">
                                        <option value="">{{ __('doc_editor_free_line') }}</option>
                                        <template x-for="p in catalog" :key="p.id">
                                            <option :value="p.id" x-text="p.label || p.name" :selected="String(p.id) === String(line.product_id || '')"></option>
                                        </template>
                                    </select>
                                    @if ($canQuickProduct)
                                        <button type="button" tabindex="-1" class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-md border border-slate-200 text-slate-500 hover:border-[var(--flow-primary)] hover:text-[var(--flow-primary)] dark:border-slate-600"
                                            @click="openQuickProduct(i)" title="{{ __('doc_editor_new_product') }}"><x-cf-icon name="plus" size="sm" /></button>
                                    @endif
                                </div>
                                <input type="text" data-line-title class="{{ $cellCls }} font-semibold" x-model="line.title" x-bind:name="'items['+i+'][title]'" maxlength="255"
                                    x-bind:required="!String(line.description || '').trim()"
                                    placeholder="{{ __('doc_line_title_ph') }}" aria-label="{{ __('doc_line_title') }}" @keydown.enter.prevent="onLineEnter(i)" />
                                <textarea data-line-desc rows="2" class="{{ $cellCls }} min-h-[2.5rem] resize-y text-xs leading-relaxed text-slate-600 dark:text-slate-300" x-model="line.description" x-bind:name="'items['+i+'][description]'" maxlength="5000"
                                    x-bind:required="!String(line.title || '').trim()"
                                    placeholder="{{ __('doc_line_description_ph') }}" aria-label="{{ __('Description') }}"></textarea>
                                <p class="text-xs text-slate-500 dark:text-slate-400" x-show="line.product_id && line.catalog_detail" x-text="line.catalog_detail" x-cloak></p>
                                <input type="hidden" x-bind:name="'items['+i+'][product_variant_id]'" x-bind:value="line.product_variant_id || ''" />
                            </div>

                            <label class="md:contents">
                                <span class="text-[11px] font-semibold uppercase text-slate-500 md:hidden">{{ __('Qty') }}</span>
                                <input type="number" min="1" step="1" class="{{ $cellCls }} text-end tabular-nums" x-model.number="line.quantity" x-bind:name="'items['+i+'][quantity]'"
                                    @input="onLineQtyInput()" @keydown.enter.prevent="onLineEnter(i)" required />
                            </label>
                            <label class="md:contents">
                                <span class="text-[11px] font-semibold uppercase text-slate-500 md:hidden">{{ __('doc_editor_col_unit') }}</span>
                                <input type="text" maxlength="32" class="{{ $cellCls }}" x-model="line.unit" x-bind:name="'items['+i+'][unit]'" placeholder="{{ __('doc_editor_unit_ph') }}" @keydown.enter.prevent="onLineEnter(i)" />
                            </label>
                            <label class="md:contents">
                                <span class="text-[11px] font-semibold uppercase text-slate-500 md:hidden">{{ __('Unit price (HT)') }}</span>
                                <input type="hidden" x-bind:name="'items['+i+'][unit_amount]'" x-bind:value="unitMinor(line)" />
                                <input type="text" inputmode="decimal" class="{{ $cellCls }} text-end tabular-nums" x-model="line.unit_display" x-bind:placeholder="pricePlaceholder()"
                                    @input="onLinePriceInput(line)" @blur="onLinePriceBlur(line)" @keydown.enter.prevent="onLineEnter(i)" required />
                            </label>
                            <label class="md:contents">
                                <span class="text-[11px] font-semibold uppercase text-slate-500 md:hidden">{{ __('doc_line_vat_col') }}</span>
                                <span class="relative block">
                                    <input type="text" inputmode="decimal" maxlength="7" class="{{ $cellCls }} pe-6 text-end tabular-nums" x-model="line.vat_rate" x-bind:name="'items['+i+'][vat_rate]'"
                                        @input="touchLines()" @keydown.enter.prevent="onLineEnter(i)" aria-label="{{ __('doc_line_vat_col') }}" data-line-vat />
                                    <span class="pointer-events-none absolute inset-y-0 end-2 flex items-center text-xs text-slate-400">%</span>
                                </span>
                            </label>
                            <div class="text-end">
                                <span class="text-[11px] font-semibold uppercase text-slate-500 md:hidden">{{ __('Line total (HT)') }}</span>
                                <p class="pt-1.5 text-sm font-semibold tabular-nums text-slate-900 dark:text-slate-100" x-text="fmtMinor(lineHt(line))"></p>
                                <p class="text-[11px] tabular-nums text-slate-400" x-text="'TTC ' + fmtMinor(lineTtc(line))"></p>
                            </div>
                            <div class="flex items-start justify-end">
                                <button type="button" tabindex="-1" class="inline-flex h-8 w-8 items-center justify-center rounded-md text-slate-400 hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-950/30"
                                    @click="removeLine(i)" title="{{ __('Remove') }}" data-line-remove><x-cf-icon name="trash" size="sm" /></button>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- 3. Add line / new product --}}
                <div class="flex flex-col gap-2 border-t border-slate-200/80 px-4 py-4 dark:border-slate-700/80 sm:flex-row sm:px-5">
                    <button type="button" data-add-line
                        class="inline-flex flex-1 items-center justify-center gap-2 rounded-xl border-2 border-dashed border-[var(--flow-primary)]/50 bg-orange-50/50 px-4 py-3 text-sm font-semibold text-[var(--flow-primary)] transition hover:border-[var(--flow-primary)] hover:bg-orange-50 dark:bg-orange-950/10 dark:hover:bg-orange-950/30"
                        @click="addLine()">
                        <x-cf-icon name="plus" size="sm" /> {{ __('doc_editor_add_line') }}
                    </button>
                    @if ($canQuickProduct)
                        <button type="button" data-quick-product-open
                            class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 transition hover:border-[var(--flow-primary)] hover:text-[var(--flow-primary)] dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200"
                            @click="openQuickProduct(null)">
                            <x-cf-icon name="circle-plus" size="sm" /> {{ __('doc_editor_new_product') }}
                        </button>
                    @endif
                </div>
                {{-- 4. Totals --}}
                <div class="grid gap-4 border-t border-slate-200/80 bg-slate-50/60 px-4 py-5 dark:border-slate-700/80 dark:bg-slate-900/30 sm:px-5 md:grid-cols-2" data-doc-editor-totals>
                    <div>
                        @include('documents.partials.discount-fields', ['doc' => $doc, 'class' => '!justify-start'])
                        <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">{{ __('doc_editor_vat_hint') }} <a href="{{ route('settings.billing-tax') }}" class="font-medium text-[var(--flow-primary)] hover:underline">{{ __('billing & tax settings') }}</a>.</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900 md:ms-auto md:w-full md:max-w-sm">
                        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ __('doc_editor_totals') }}</h3>
                        <dl class="mt-3 space-y-2 text-sm text-slate-700 dark:text-slate-300">
                            @include('documents.partials.discount-rows', ['rowClass' => 'flex justify-between gap-4'])
                            <div class="flex justify-between gap-4">
                                <dt x-text="totals.discount > 0 ? @js(__('Net total (ex. VAT)')) : @js(__('Subtotal (ex. VAT)'))">{{ __('Subtotal (ex. VAT)') }}</dt>
                                <dd class="font-medium tabular-nums" x-text="fmtMinor(totals.subtotal)"></dd>
                            </div>
                            <template x-for="row in totals.breakdown" :key="row.rate">
                                <div class="flex justify-between gap-4" data-vat-row x-show="row.vat > 0 || totals.breakdown.length > 1">
                                    <dt><span>{{ __('VAT') }}</span> <span x-text="row.label"></span></dt>
                                    <dd class="font-medium tabular-nums" x-text="fmtMinor(row.vat)"></dd>
                                </div>
                            </template>
                            <div class="flex justify-between gap-4" x-show="taxPreview.fiscal_stamp_enabled && totals.stamp > 0" x-cloak>
                                <dt>{{ __('Fiscal stamp') }}</dt>
                                <dd class="font-medium tabular-nums" x-text="fmtMinor(totals.stamp)"></dd>
                            </div>
                            <div class="flex items-baseline justify-between gap-4 border-t border-slate-200 pt-3 dark:border-slate-700">
                                <dt class="text-base font-bold text-slate-900 dark:text-white">{{ __('Total (inc. VAT)') }}</dt>
                                <dd class="text-xl font-bold tabular-nums text-slate-900 dark:text-white"><span x-text="fmtMinor(totals.total)"></span> <span class="text-sm font-semibold text-slate-500" x-text="currencyCode"></span></dd>
                            </div>
                        </dl>
                    </div>
                </div>
                <x-input-error class="px-5 pb-4" :messages="$errors->get('items')" />
            </section>

            {{-- Options avancées --}}
            <details class="group rounded-2xl border border-slate-200/90 bg-white shadow-sm dark:border-slate-700/80 dark:bg-slate-900/40" @if ($advancedOpen) open @endif data-doc-editor-advanced>
                <summary class="flex cursor-pointer list-none items-center gap-2 px-5 py-4 font-semibold text-slate-900 dark:text-white">
                    <x-cf-icon name="sliders" size="sm" />
                    {{ __('doc_editor_advanced') }}
                    <span class="text-xs font-normal text-slate-500">{{ $isQuote ? __('doc_editor_advanced_hint_quote') : __('doc_editor_advanced_hint_invoice') }}</span>
                    <span class="ms-auto text-slate-400 transition group-open:rotate-180"><x-cf-icon name="chevron-down" size="sm" /></span>
                </summary>
                <div class="space-y-5 border-t border-slate-200/80 px-5 py-5 dark:border-slate-700/80">
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div>
                            <label for="currency" class="{{ $labelCls }}">{{ __('Currency') }}</label>
                            <x-currency-select id="currency" name="currency" :options="$currencyOptions" :value="$currencyValue" x-on:change="syncCurrencyMoney($event.target.value)" />
                            <x-input-error class="mt-1" :messages="$errors->get('currency')" />
                        </div>
                        @if ($isQuote)
                            <div>
                                <label for="advance_percent" class="{{ $labelCls }}">{{ __('Advance percent') }}</label>
                                <input id="advance_percent" name="advance_percent" type="number" step="0.01" min="0" max="100" value="{{ old('advance_percent', $doc?->advance_percent) }}" class="mt-1 {{ $inputCls }}" />
                            </div>
                            <div>
                                <label for="advance_amount_major" class="{{ $labelCls }}">{{ __('Advance amount (optional override)') }}</label>
                                <input id="advance_amount_major" name="advance_amount_major" type="text" inputmode="decimal" value="{{ old('advance_amount_major', $doc?->advance_amount_minor ? flowdesk_major_amount_for_locale_input((int) $doc->advance_amount_minor, $docCurrency ?? $currencyValue) : '') }}" class="mt-1 {{ $inputCls }}" />
                            </div>
                        @endif
                    </div>
                    @if ($isQuote && ! empty($paymentModalities))
                        <div>
                            <p class="{{ $labelCls }}">{{ __('Payment modalities') }}</p>
                            <div class="mt-2 flex flex-wrap gap-x-5 gap-y-2">
                                @foreach ($paymentModalities as $mod)
                                    <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                                        <input type="checkbox" name="payment_terms[]" value="{{ $mod['key'] }}" @checked(in_array($mod['key'], (array) ($selectedTerms ?? []), true)) class="rounded border-slate-300 text-[var(--flow-primary)]" />
                                        {{ $mod['label'] }}
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endif
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="customer_notes" class="{{ $labelCls }}">{{ __('Customer notes (shown on PDF)') }}</label>
                            <textarea id="customer_notes" name="customer_notes" rows="3" class="mt-1 {{ $inputCls }}">{{ $customerNotes }}</textarea>
                            <x-input-error class="mt-1" :messages="$errors->get('customer_notes')" />
                        </div>
                        <div>
                            <label for="internal_notes" class="{{ $labelCls }}">{{ __('Internal notes (team only)') }}</label>
                            <textarea id="internal_notes" name="internal_notes" rows="3" class="mt-1 {{ $inputCls }}">{{ $internalNotes }}</textarea>
                            <x-input-error class="mt-1" :messages="$errors->get('internal_notes')" />
                        </div>
                    </div>
                    @unless ($isQuote)
                        @include('invoices.partials.recurrence-fields', ['invoice' => $doc])
                    @endunless
                </div>
            </details>
        </div>

    </div>

    {{-- Sticky action bar --}}
    <div class="sticky bottom-0 z-30 mx-auto mt-6 max-w-12xl rounded-t-2xl border border-b-0 border-slate-200 bg-white/95 shadow-[0_-8px_24px_-12px_rgba(15,23,42,0.25)] backdrop-blur dark:border-slate-700 dark:bg-slate-900/95" data-doc-editor-actions>
        <div class="flex flex-wrap items-center gap-2 px-4 py-3 sm:px-5">
            <div class="me-auto hidden text-sm text-slate-600 dark:text-slate-300 sm:block">
                {{ __('Total (inc. VAT)') }} : <strong class="tabular-nums text-slate-900 dark:text-white" x-text="fmtMinor(totals.total) + ' ' + currencyCode"></strong>
            </div>
            @if ($isQuote)
                <label class="me-2 inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300" title="{{ __('quote_send_to_client_hint') }}">
                    <input type="checkbox" name="send_to_client" value="1" data-send-to-client class="rounded border-slate-300 text-[var(--flow-primary)]" @checked(old('send_to_client')) />
                    {{ __('Send to client by email') }}
                </label>
            @endif
            <a href="{{ $cancelUrl }}" class="inline-flex items-center rounded-lg px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">{{ __('Cancel') }}</a>
            <button type="submit" formaction="{{ $previewUrl }}" formtarget="_blank" formnovalidate data-doc-preview
                class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
                <x-cf-icon name="file-pdf" size="sm" /> {{ $previewLabel }}
            </button>
            @unless ($isEdit)
                <button type="submit" data-doc-save-draft
                    class="inline-flex items-center gap-2 rounded-lg border border-[var(--flow-primary)]/50 bg-white px-4 py-2 text-sm font-semibold text-[var(--flow-primary)] hover:bg-orange-50 dark:bg-slate-800"
                    @click="@if ($isQuote) $root.querySelector('[data-send-to-client]').checked = false @else document.getElementById('status').value = 'draft' @endif">
                    <x-cf-icon name="floppy-disk" size="sm" /> {{ __('doc_editor_save_draft') }}
                </button>
            @endunless
            <button type="submit" data-nova-voice-save
                class="inline-flex items-center gap-2 rounded-lg bg-flow-primary px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-flow-primary-hover focus:outline-none focus:ring-2 focus:ring-[var(--flow-primary)] focus:ring-offset-2 dark:focus:ring-offset-slate-900">
                <x-cf-icon name="check" size="sm" />
                {{ $isEdit ? ($isQuote ? __('Save quote') : __('Update invoice')) : ($isQuote ? __('Create quote') : __('doc_editor_create_invoice')) }}
            </button>
        </div>
    </div>

    {{-- Quick add client (right slide-over) --}}
    @if (! empty($clientsStoreAvailable))
        <x-client-document-quick-modal :intro="$isQuote ? __('Saves immediately and adds to the list for this quote.') : __('Saves immediately and adds to the list for this invoice.')" />
    @endif

    {{-- Quick add product / service (right slide-over) --}}
    @if ($canQuickProduct)
        <div x-show="qpOpen" x-cloak class="fixed inset-0 z-[70]" @keydown.escape.window="qpOpen = false" data-quick-product-aside>
            <div class="absolute inset-0 bg-slate-900/30" @click="qpOpen = false" x-show="qpOpen" x-transition.opacity></div>
            <aside class="absolute inset-y-0 end-0 flex w-full max-w-md flex-col bg-white shadow-2xl dark:bg-slate-900"
                x-show="qpOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
                x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
                role="dialog" aria-modal="true" aria-labelledby="cf_qp_title">
                <div class="flex items-center gap-3 border-b border-slate-200 px-5 py-4 dark:border-slate-700">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-orange-100 text-[var(--flow-primary)] dark:bg-orange-950/40"><x-cf-icon name="boxes-stacked" size="sm" /></span>
                    <div class="min-w-0">
                        <h3 id="cf_qp_title" class="font-semibold text-slate-900 dark:text-white">{{ __('doc_editor_new_product') }}</h3>
                        <p class="text-xs text-slate-500">{{ __('doc_editor_qp_intro') }}</p>
                    </div>
                    <button type="button" class="ms-auto rounded-md p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800" @click="qpOpen = false" title="{{ __('Close') }}"><x-cf-icon name="xmark" size="sm" /></button>
                </div>
                <div class="flex-1 space-y-4 overflow-y-auto px-5 py-5" @keydown.enter.prevent="$event.target.tagName !== 'TEXTAREA' && submitQuickProduct()">
                    <div class="grid grid-cols-2 gap-2 rounded-xl bg-slate-100 p-1 dark:bg-slate-800">
                        <button type="button" class="rounded-lg px-3 py-1.5 text-sm font-semibold" :class="qp.kind === 'service' ? 'bg-white text-[var(--flow-primary)] shadow-sm dark:bg-slate-900' : 'text-slate-600 dark:text-slate-300'" @click="qp.kind = 'service'; qp.unit = ''">{{ __('catalog_kind_service') }}</button>
                        <button type="button" class="rounded-lg px-3 py-1.5 text-sm font-semibold" :class="qp.kind === 'product' ? 'bg-white text-[var(--flow-primary)] shadow-sm dark:bg-slate-900' : 'text-slate-600 dark:text-slate-300'" @click="qp.kind = 'product'; qp.unit = ''">{{ __('catalog_kind_product') }}</button>
                    </div>
                    <div>
                        <label for="cf_qp_name" class="{{ $labelCls }}">{{ __('Name') }} *</label>
                        <input id="cf_qp_name" type="text" maxlength="255" x-model="qp.name" class="mt-1 {{ $inputCls }}" autocomplete="off" />
                    </div>
                    <div>
                        <label for="cf_qp_description" class="{{ $labelCls }}">{{ __('Description') }}</label>
                        <textarea id="cf_qp_description" rows="3" x-model="qp.description" class="mt-1 {{ $inputCls }}"></textarea>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="cf_qp_price" class="{{ $labelCls }}">{{ __('doc_editor_qp_price') }} (<span x-text="currencyCode"></span>)</label>
                            <input id="cf_qp_price" type="text" inputmode="decimal" x-model="qp.price" :placeholder="pricePlaceholder()" class="mt-1 {{ $inputCls }} text-end tabular-nums" />
                        </div>
                        <div>
                            <label for="cf_qp_tax" class="{{ $labelCls }}">{{ __('doc_line_vat_col') }}</label>
                            <input id="cf_qp_tax" type="text" inputmode="decimal" x-model="qp.tax_rate" class="mt-1 {{ $inputCls }} text-end tabular-nums" />
                        </div>
                        <div>
                            <label for="cf_qp_unit" class="{{ $labelCls }}">{{ __('doc_editor_col_unit') }}</label>
                            <select id="cf_qp_unit" x-model="qp.unit" class="mt-1 {{ $inputCls }}">
                                <option value="">—</option>
                                <template x-for="u in qpUnits()" :key="u.value">
                                    <option :value="u.value" x-text="u.label"></option>
                                </template>
                            </select>
                        </div>
                        <div>
                            <label for="cf_qp_category" class="{{ $labelCls }}">{{ __('doc_editor_qp_category') }}</label>
                            <input id="cf_qp_category" type="text" maxlength="100" x-model="qp.category" class="mt-1 {{ $inputCls }}" autocomplete="off" />
                        </div>
                    </div>
                    <p class="text-xs text-slate-500">{{ __('doc_editor_qp_vat_hint') }}</p>
                    <p class="rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-700 dark:bg-rose-950/30 dark:text-rose-300" x-show="qpError" x-text="qpError" x-cloak></p>
                </div>
                <div class="flex items-center justify-end gap-2 border-t border-slate-200 px-5 py-4 dark:border-slate-700">
                    <button type="button" class="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800" @click="qpOpen = false">{{ __('Cancel') }}</button>
                    <button type="button" class="inline-flex items-center gap-2 rounded-lg bg-flow-primary px-4 py-2 text-sm font-semibold text-white hover:bg-flow-primary-hover disabled:opacity-50" :disabled="qpLoading || !String(qp.name || '').trim()" @click="submitQuickProduct()" data-quick-product-save>
                        <x-cf-icon name="check" size="sm" />
                        <span x-show="!qpLoading">{{ __('doc_editor_qp_save') }}</span>
                        <span x-show="qpLoading" x-cloak>{{ __('Saving…') }}</span>
                    </button>
                </div>
            </aside>
        </div>
    @endif
</form>
