<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-800 dark:text-slate-100 leading-tight">{{ __('Edit invoice') }}</h2>
    </x-slot>

    @php
        $itemsDefault = $invoice->items->map(fn ($i) => [
            'title' => $i->title,
            'description' => $i->description,
            'quantity' => $i->quantity,
            'unit_amount' => $i->unit_amount,
            'product_id' => $i->product_id,
            'product_variant_id' => $i->product_variant_id,
            'unit' => $i->unit,
            'vat_rate' => $i->vat_rate,
        ])->values()->all();
        $itemsRaw = old('items', $itemsDefault);
        $invoiceCurrency = strtoupper((string) old('currency', flowdesk_invoice_currency($invoice)));
        $defaultVat = (float) ($taxPreview['vat_percent'] ?? 0);
        $itemsOld = collect($itemsRaw)->map(function ($row) use ($invoiceCurrency, $defaultVat) {
            $minor = (int) ($row['unit_amount'] ?? 0);

            return [
                'title' => (string) ($row['title'] ?? ''),
                'description' => $row['description'] ?? '',
                'quantity' => (int) ($row['quantity'] ?? 1),
                'unit_amount_minor' => $minor,
                'unit_major' => flowdesk_minor_to_major($minor, $invoiceCurrency),
                'product_id' => $row['product_id'] ?? null,
                'product_variant_id' => $row['product_variant_id'] ?? null,
                'unit' => $row['unit'] ?? '',
                'vat_rate' => flowdesk_vat_rate_input($row['vat_rate'] ?? null, $defaultVat),
            ];
        })->values()->all();
        $company = auth()->user()->company;
        $currencyMoneyMeta = flowdesk_currency_money_meta_for_js($company?->default_currency ?? flowdesk_default_currency(), $invoice->currency);
        $catalogForJs = flowdesk_document_catalog_for_js($catalogProducts ?? []);
        $invoiceAiConfig = [
            'url' => route('invoices.ai-line-items', $invoice),
            'scanUrl' => route('invoices.ai-line-items.scan', $invoice),
            'draft' => false,
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
                'doc' => $invoice,
                'isEdit' => true,
                'formAction' => route('invoices.update', $invoice),
                'xData' => $xData,
                'clients' => $clients,
                'currencyOptions' => $currencyOptions,
                'cancelUrl' => route('invoices.show', $invoice),
                'previewUrl' => route('invoices.draft-preview'),
                'referencePreview' => $referencePreview ?? null,
                'clientsStoreAvailable' => true,
            ])
        </div>
    </div>
</x-app-layout>
