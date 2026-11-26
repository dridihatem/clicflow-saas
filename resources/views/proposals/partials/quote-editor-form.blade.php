@php
    $doc = $proposal ?? null;
    $isEdit = $doc !== null;
    $formAction = $isEdit ? route('proposals.update', $doc) : route('proposals.store');
    $workspaceCurrency = strtoupper((string) (auth()->user()->company?->default_currency ?? flowdesk_default_currency()));
    $docCurrency = strtoupper((string) old('currency', $doc?->currency ?? $workspaceCurrency));

    if ($isEdit && $doc->items->isNotEmpty()) {
        $defaultItems = $doc->items->map(fn ($row) => [
            'title' => $row->title,
            'description' => $row->description,
            'quantity' => $row->quantity,
            'unit_amount' => $row->unit_amount,
            'total_amount' => $row->total_amount,
            'product_id' => $row->product_id,
            'product_variant_id' => $row->product_variant_id,
            'unit' => $row->unit,
            'vat_rate' => $row->vat_rate,
        ])->all();
    } elseif ($isEdit) {
        $defaultItems = [['description' => $doc->name, 'quantity' => 1, 'unit_amount' => (int) $doc->subtotal_amount ?: (int) $doc->amount, 'total_amount' => (int) $doc->subtotal_amount ?: (int) $doc->amount, 'product_id' => null, 'product_variant_id' => null, 'unit' => null]];
    } else {
        $defaultItems = [['description' => '', 'quantity' => 1, 'unit_amount' => 0, 'total_amount' => 0, 'product_id' => null, 'product_variant_id' => null, 'unit' => null]];
        if (is_array($assistantPrefill ?? null) && ! empty($assistantPrefill['items'])) {
            $defaultItems = collect($assistantPrefill['items'])->map(function (array $row) use ($docCurrency) {
                $qty = max(1, (int) ($row['quantity'] ?? 1));
                $major = (float) ($row['unit_major'] ?? 0);
                $minor = $major > 0 ? flowdesk_decimal_to_minor((string) $major, $docCurrency) : 0;

                $label = flowdesk_split_line_label($row['title'] ?? null, $row['description'] ?? '');

                return [
                    'title' => (string) ($label['title'] ?? ''),
                    'description' => $label['description'],
                    'quantity' => $qty,
                    'unit_amount' => $minor,
                    'total_amount' => $minor * $qty,
                    'product_id' => null,
                    'product_variant_id' => null,
                    'unit' => null,
                ];
            })->all();
        }
    }

    $itemsRaw = old('items', $defaultItems);
    $defaultVat = (float) ($taxPreview['vat_percent'] ?? 0);
    $itemsOld = collect($itemsRaw)->map(function ($row) use ($docCurrency, $defaultVat) {
        $minor = (int) ($row['unit_amount'] ?? 0);
        if ($minor <= 0) {
            $qty = max(1, (int) ($row['quantity'] ?? 1));
            $lineTotal = (int) ($row['total_amount'] ?? 0);
            if ($lineTotal > 0) {
                $minor = intdiv($lineTotal, $qty);
            }
        }

        return [
            'title' => (string) ($row['title'] ?? ''),
            'description' => $row['description'] ?? '',
            'quantity' => (int) ($row['quantity'] ?? 1),
            'unit_amount_minor' => $minor,
            'unit_major' => flowdesk_minor_to_major($minor, $docCurrency),
            'unit_display' => flowdesk_major_amount_for_locale_input($minor, $docCurrency),
            'product_id' => $row['product_id'] ?? null,
            'product_variant_id' => $row['product_variant_id'] ?? null,
            'unit' => $row['unit'] ?? '',
            'vat_rate' => flowdesk_vat_rate_input($row['vat_rate'] ?? null, $defaultVat),
        ];
    })->values()->all();

    $catalogForJs = flowdesk_document_catalog_for_js($catalogProducts ?? []);

    $selectedTerms = old('payment_terms', $doc?->payment_terms_json['modalities'] ?? []);

    $currencyMoneyMeta = flowdesk_currency_money_meta_for_js(
        auth()->user()->company?->default_currency ?? flowdesk_default_currency(),
        $doc?->currency
    );
    $localeSep = flowdesk_locale_amount_separators();
    $aiConfig = [
        'url' => $isEdit
            ? route('proposals.ai-line-items', $doc)
            : route('proposals.ai-line-items.draft'),
        'scanUrl' => $isEdit
            ? route('proposals.ai-line-items.scan', $doc)
            : route('proposals.ai-line-items.scan.draft'),
        'draft' => ! $isEdit,
        'speakUrl' => route('assistant.speak'),
        'workingSpeech' => __('nova_ai_working_speech'),
        'errBrief' => __('quote_ai_brief_required'),
        'errNetwork' => __('quote_ai_network_error'),
        'errEmpty' => __('quote_ai_empty_lines'),
        'errScanFile' => __('document_scan_file_required'),
        'errScanNetwork' => __('document_scan_network_error'),
        'errScanEmpty' => __('document_scan_empty_lines'),
        'initialBrief' => is_array($assistantPrefill ?? null) ? (string) ($assistantPrefill['outline'] ?? '') : '',
    ];
    $xData = 'quoteForm('.e(\Illuminate\Support\Js::from($itemsOld)).', '.e(\Illuminate\Support\Js::from($taxPreview)).', '.e(\Illuminate\Support\Js::from($currencyMoneyMeta)).', '.e(\Illuminate\Support\Js::from($localeSep)).', '.e(\Illuminate\Support\Js::from($aiConfig)).', '.e(\Illuminate\Support\Js::from(route('clients.quick-store'))).', '.e(\Illuminate\Support\Js::from($catalogForJs)).', '.e(\Illuminate\Support\Js::from(flowdesk_document_editor_config())).')';
@endphp

@include('documents.partials.document-editor', [
    'kind' => 'quote',
    'doc' => $doc,
    'isEdit' => $isEdit,
    'formAction' => $formAction,
    'xData' => $xData,
    'clients' => $clients,
    'selectedClientId' => (string) ($prefillClientId ?? ''),
    'currencyOptions' => $currencyOptions,
    'cancelUrl' => $isEdit ? route('proposals.show', $doc) : route('proposals.index'),
    'previewUrl' => route('proposals.draft-preview'),
    'referencePreview' => $referencePreview ?? null,
    'projects' => $projects ?? [],
    'nameValue' => old('name', $doc?->name ?? (is_array($assistantPrefill ?? null) ? ($assistantPrefill['quote_name'] ?? '') : '')),
    'customerNotesDefault' => is_array($assistantPrefill ?? null) ? ($assistantPrefill['outline'] ?? '') : '',
    'paymentModalities' => $paymentModalities ?? [],
    'selectedTerms' => $selectedTerms,
    'docCurrency' => $docCurrency,
    'aiPanelOpen' => filled($aiConfig['initialBrief'] ?? ''),
    'clientsStoreAvailable' => true,
])
