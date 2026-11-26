<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-800 dark:text-slate-100 leading-tight">{{ __('Invoice documents') }}</h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-3xl w-full sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-6 rounded-xl border border-emerald-200/80 bg-emerald-50/90 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900/40 dark:bg-emerald-950/50 dark:text-emerald-100">{{ session('status') }}</div>
            @endif

            <div class="rounded-2xl border border-slate-200/80 bg-white/80 p-8 shadow-xl shadow-slate-900/5 ring-1 ring-slate-900/5 backdrop-blur-sm dark:border-slate-700/80 dark:bg-slate-900/50 dark:ring-white/10">
                <p class="text-sm text-slate-600 dark:text-slate-400">{{ __('Customize intro and footer for invoice emails and PDFs. HTML is allowed (trusted workspace admins only).') }}</p>

                <form method="POST" action="{{ route('settings.invoice-documents.update') }}" class="mt-8 space-y-6">
                    @csrf
                    @method('PUT')

                    <div>
                        <x-input-label for="invoice_pdf_template" :value="__('settings_invoice_pdf_template')" />
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('settings_invoice_pdf_template_help') }}</p>

                        @php
                            $selectedTemplate = old('invoice_pdf_template', $templates['invoice_pdf_template'] ?? 'classic');
                        @endphp

                        <div class="mt-3 grid gap-3 sm:grid-cols-2" role="radiogroup" aria-labelledby="invoice_pdf_template">
                            @foreach ($templateCards as $value => $card)
                                <label class="group relative flex cursor-pointer flex-col gap-3 rounded-xl border border-slate-200 bg-white p-4 transition hover:border-slate-300 has-[:checked]:border-teal-500 has-[:checked]:bg-teal-50/60 has-[:checked]:ring-2 has-[:checked]:ring-teal-500/30 dark:border-slate-700 dark:bg-slate-900/40 dark:hover:border-slate-600 dark:has-[:checked]:border-teal-400 dark:has-[:checked]:bg-teal-950/40">
                                    <input
                                        type="radio"
                                        name="invoice_pdf_template"
                                        value="{{ $value }}"
                                        class="sr-only"
                                        @checked($selectedTemplate === $value)
                                    />
                                    <div class="flex items-start justify-between gap-2">
                                        <span class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $card['label'] }}</span>
                                        <span class="mt-0.5 inline-flex h-4 w-4 shrink-0 items-center justify-center rounded-full border border-slate-300 text-transparent group-has-[:checked]:border-teal-500 group-has-[:checked]:bg-teal-500 group-has-[:checked]:text-white dark:border-slate-600">
                                            <i class="fa-solid fa-check text-[8px]" aria-hidden="true"></i>
                                        </span>
                                    </div>                                    <div class="flex gap-1.5">
                                        <span class="h-7 w-7 rounded-md border border-slate-200 shadow-inner dark:border-slate-600" style="background: {{ $card['primary_color'] }}" title="primary"></span>
                                        <span class="h-7 w-7 rounded-md border border-slate-200 shadow-inner dark:border-slate-600" style="background: {{ $card['accent_color'] }}" title="accent"></span>
                                        <span class="h-7 w-7 rounded-md border border-slate-200 shadow-inner dark:border-slate-600" style="background: {{ $card['table_header_bg'] }}" title="header"></span>
                                    </div>
                                    <div class="h-1.5 overflow-hidden rounded-full" style="background: {{ $card['table_header_bg'] }}">
                                        <div class="h-full w-2/5 rounded-full" style="background: {{ $card['accent_color'] }}"></div>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                        <x-input-error :messages="$errors->get('invoice_pdf_template')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="invoice_email_intro" :value="__('Email intro (HTML)')" />
                        <textarea id="invoice_email_intro" name="invoice_email_intro" rows="5" class="mt-1 block w-full rounded-lg border-slate-300 font-mono text-sm shadow-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100">{{ old('invoice_email_intro', $templates['invoice_email_intro'] ?? '') }}</textarea>
                    </div>
                    <div>
                        <x-input-label for="invoice_email_footer" :value="__('Email footer (HTML)')" />
                        <textarea id="invoice_email_footer" name="invoice_email_footer" rows="4" class="mt-1 block w-full rounded-lg border-slate-300 font-mono text-sm shadow-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100">{{ old('invoice_email_footer', $templates['invoice_email_footer'] ?? '') }}</textarea>
                    </div>
                    <div>
                        <x-input-label for="invoice_pdf_footer" :value="__('PDF footer (HTML)')" />
                        <textarea id="invoice_pdf_footer" name="invoice_pdf_footer" rows="3" class="mt-1 block w-full rounded-lg border-slate-300 font-mono text-sm shadow-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100">{{ old('invoice_pdf_footer', $templates['invoice_pdf_footer'] ?? '') }}</textarea>
                    </div>

                    <div class="border-t border-slate-200/80 pt-6 dark:border-slate-700">
                        <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ __('Invoice reference (numbering)') }}</h3>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('New invoices use: prefix, sequence (zero-padded), then year — e.g. Fac-001-2026.') }}</p>
                        <div class="mt-4 grid gap-4 sm:grid-cols-2">
                            <div>
                                <x-input-label for="invoice_reference_prefix" :value="__('Prefix')" />
                                <x-text-input id="invoice_reference_prefix" name="invoice_reference_prefix" type="text" class="mt-1 block w-full" :value="old('invoice_reference_prefix', $templates['invoice_reference_prefix'] ?? 'INV')" required maxlength="32" />
                                <x-input-error class="mt-2" :messages="$errors->get('invoice_reference_prefix')" />
                            </div>
                            <div>
                                <x-input-label for="invoice_reference_pad" :value="__('Sequence length (digits)')" />
                                <x-text-input id="invoice_reference_pad" name="invoice_reference_pad" type="number" min="1" max="12" class="mt-1 block w-full" :value="old('invoice_reference_pad', $templates['invoice_reference_pad'] ?? 3)" required />
                                <x-input-error class="mt-2" :messages="$errors->get('invoice_reference_pad')" />
                            </div>
                        </div>
                        <div class="mt-4">
                            <x-input-label for="invoice_reference_start" :value="__('Start number (first sequence)')" />
                            <x-text-input id="invoice_reference_start" name="invoice_reference_start" type="number" min="1" class="mt-1 block w-full" :value="old('invoice_reference_start', $templates['invoice_reference_start'] ?? 1)" required />
                            <x-input-error class="mt-2" :messages="$errors->get('invoice_reference_start')" />
                        </div>
                        <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">{{ __('Next example') }}: <span class="font-mono font-medium text-slate-700 dark:text-slate-300">{{ $referencePreview }}</span></p>
                    </div>

                    <div class="border-t border-slate-200/80 pt-6 dark:border-slate-700">
                        <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ __('Quote reference (numbering)') }}</h3>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('New quotes use: prefix, sequence (zero-padded), then year — e.g. Dev-001-2026.') }}</p>
                        <div class="mt-4 grid gap-4 sm:grid-cols-2">
                            <div>
                                <x-input-label for="quote_reference_prefix" :value="__('Prefix')" />
                                <x-text-input id="quote_reference_prefix" name="quote_reference_prefix" type="text" class="mt-1 block w-full" :value="old('quote_reference_prefix', $templates['quote_reference_prefix'] ?? 'DEV')" required maxlength="32" />
                                <x-input-error class="mt-2" :messages="$errors->get('quote_reference_prefix')" />
                            </div>
                            <div>
                                <x-input-label for="quote_reference_pad" :value="__('Sequence length (digits)')" />
                                <x-text-input id="quote_reference_pad" name="quote_reference_pad" type="number" min="1" max="12" class="mt-1 block w-full" :value="old('quote_reference_pad', $templates['quote_reference_pad'] ?? 3)" required />
                                <x-input-error class="mt-2" :messages="$errors->get('quote_reference_pad')" />
                            </div>
                        </div>
                        <div class="mt-4">
                            <x-input-label for="quote_reference_start" :value="__('Start number (first sequence)')" />
                            <x-text-input id="quote_reference_start" name="quote_reference_start" type="number" min="1" class="mt-1 block w-full" :value="old('quote_reference_start', $templates['quote_reference_start'] ?? 1)" required />
                            <x-input-error class="mt-2" :messages="$errors->get('quote_reference_start')" />
                        </div>
                        <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">{{ __('Next example') }}: <span class="font-mono font-medium text-slate-700 dark:text-slate-300">{{ $quoteReferencePreview }}</span></p>
                    </div>

                    <x-primary-button>{{ __('Save') }}</x-primary-button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
