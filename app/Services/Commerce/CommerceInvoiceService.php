<?php

namespace App\Services\Commerce;

use App\Enums\InvoiceStatus;
use App\Models\CommerceOrder;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Services\InvoiceReferenceService;
use Illuminate\Support\Facades\DB;

class CommerceInvoiceService
{
    public function __construct(
        private InvoiceReferenceService $references,
    ) {}

    public function createFromOrder(CommerceOrder $order): Invoice
    {
        $order->loadMissing(['items', 'company', 'client']);

        if ($order->invoice_id) {
            return Invoice::query()->withoutGlobalScopes()->findOrFail($order->invoice_id);
        }

        $company = $order->company;
        abort_if(! $company instanceof Company, 404);

        return DB::transaction(function () use ($order, $company) {
            $subtotal = (int) $order->total_minor;

            $invoice = Invoice::query()->withoutGlobalScopes()->create([
                'company_id' => $company->id,
                'client_id' => $order->client_id,
                'proposal_id' => null,
                'project_id' => null,
                'number' => null,
                'status' => InvoiceStatus::Sent,
                'subtotal_amount' => $subtotal,
                'vat_amount' => 0,
                'fiscal_stamp_amount' => 0,
                'amount' => $subtotal,
                'currency' => $order->currency,
                'due_date' => now()->addDays(14)->toDateString(),
                'customer_notes' => __('Generated from commerce order'),
            ]);

            foreach ($order->items as $item) {
                InvoiceItem::query()->withoutGlobalScopes()->create([
                    'company_id' => $company->id,
                    'invoice_id' => $invoice->id,
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit_amount' => $item->unit_price_minor,
                    'total_amount' => $item->total_minor,
                ]);
            }

            $this->references->assignNextNumber($invoice, $company);

            $order->forceFill(['invoice_id' => $invoice->id])->save();

            return $invoice->fresh();
        });
    }
}
