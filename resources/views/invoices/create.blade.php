<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-800 dark:text-slate-100 leading-tight">{{ __('New invoice') }}</h2>
    </x-slot>

    @php
        $prefillItems = isset($invoiceProjectPrefill) && is_array($invoiceProjectPrefill['items'] ?? null) ? $invoiceProjectPrefill['items'] : null;
        $itemsRaw = old('items', $prefillItems ?? [['description' => '', 'quantity' => 1, 'unit_amount' => 0]]);
        $workspaceCurrency = strtoupper((string) (auth()->user()->company?->default_currency ?? flowdesk_default_currency()));
        $invoiceCurrency = strtoupper((string) old('currency', $workspaceCurrency));
        $usedProjectPrefillLines = ! session()->hasOldInput('items')
            && ! empty(($invoiceProjectPrefill ?? [])['project_id'] ?? null);
        $minorCurrencyForLines = $usedProjectPrefillLines ? $workspaceCurrency : $invoiceCurrency;
        $defaultVat = (float) ($taxPreview['vat_percent'] ?? 0);
        $itemsOld = collect($itemsRaw)->map(function ($row) use ($minorCurrencyForLines, $defaultVat) {
            $minor = (int) ($row['unit_amount'] ?? 0);

            return [
                'title' => (string) ($row['title'] ?? ''),
                'description' => $row['description'] ?? '',
                'quantity' => (int) ($row['quantity'] ?? 1),
                'unit_amount_minor' => $minor,
                'unit_major' => flowdesk_minor_to_major($minor, $minorCurrencyForLines),
                'product_id' => $row['product_id'] ?? null,
                'product_variant_id' => $row['product_variant_id'] ?? null,
                'unit' => $row['unit'] ?? '',
                'vat_rate' => flowdesk_vat_rate_input($row['vat_rate'] ?? null, $defaultVat),
            ];
        })->values()->all();
        $currencyMoneyMeta = flowdesk_currency_money_meta_for_js(
            auth()->user()->company?->default_currency ?? flowdesk_default_currency(),
            $invoiceCurrency
        );
        $catalogForJs = flowdesk_document_catalog_for_js($catalogProducts ?? []);
        $invoiceAiConfig = [
            'url' => route('invoices.ai-line-items.draft'),
            'scanUrl' => route('invoices.ai-line-items.scan.draft'),
            'draft' => true,
            'speakUrl' => route('assistant.speak'),
            'workingSpeech' => __('nova_ai_working_speech'),
            'errBrief' => __('invoice_ai_brief_required'),
            'errNetwork' => __('invoice_ai_network_error'),
            'errEmpty' => __('invoice_ai_empty_lines'),
            'errScanFile' => __('document_scan_file_required'),
            'errScanNetwork' => __('document_scan_network_error'),
            'errScanEmpty' => __('document_scan_empty_lines'),
        ];
        $xData = 'invoiceForm('.e(\Illuminate\Support\Js::from($itemsOld)).', '.e(\Illuminate\Support\Js::from($taxPreview)).', '.e(\Illuminate\Support\Js::from(route('clients.quick-store'))).', '.e(\Illuminate\Support\Js::from($currencyMoneyMeta)).', '.e(\Illuminate\Support\Js::from(flowdesk_locale_amount_separators())).', '.e(\Illuminate\Support\Js::from($invoiceAiConfig)).', '.e(\Illuminate\Support\Js::from($catalogForJs)).', '.e(\Illuminate\Support\Js::from(flowdesk_document_editor_config())).')';
    @endphp

    <div class="py-6">
        <div class="max-w-12xl w-full px-4 sm:px-6 lg:px-8">
            @include('documents.partials.document-editor', [
                'kind' => 'invoice',
                'doc' => null,
                'isEdit' => false,
                'formAction' => route('invoices.store'),
                'xData' => $xData,
                'clients' => $clients,
                'selectedClientId' => (string) ($prefillClientId ?? ''),
                'currencyOptions' => $currencyOptions,
                'cancelUrl' => route('invoices.index'),
                'previewUrl' => route('invoices.draft-preview'),
                'referencePreview' => $referencePreview ?? null,
                'invoiceProjectPrefill' => $invoiceProjectPrefill ?? null,
                'clientModeDefault' => old('client_mode', 'pick'),
                'clientsStoreAvailable' => true,
            ])
        </div>
    </div>
</x-app-layout>
