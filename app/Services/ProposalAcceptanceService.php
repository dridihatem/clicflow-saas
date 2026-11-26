<?php

namespace App\Services;

use App\Enums\InvoiceKind;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentEntryKind;
use App\Enums\PaymentStatus;
use App\Enums\ProposalStatus;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Proposal;
use App\Services\Commerce\StockService;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProposalAcceptanceService
{
    public function __construct(
        private InvoiceTotalsService $totalsService,
        private InvoiceReferenceService $referenceService,
        private StockService $stock,
        private DepositReceiptPdfService $receipts,
    ) {}

    /**
     * Accept quote and create deposit invoice when an advance is configured.
     *
     * @return array{proposal: Proposal, depositInvoice: ?Invoice}
     */
    public function accept(Proposal $proposal, Company $company): array
    {
        abort_if((string) $proposal->company_id !== (string) $company->id, 403);

        if ($proposal->status === ProposalStatus::Accepted && $proposal->deposit_invoice_id) {
            return [
                'proposal' => $proposal->fresh(['depositInvoice']),
                'depositInvoice' => $proposal->depositInvoice,
            ];
        }

        if ($proposal->status === ProposalStatus::Rejected) {
            abort(422, __('Cannot accept a rejected quote.'));
        }

        return DB::transaction(function () use ($proposal, $company) {
            $proposal->update(['status' => ProposalStatus::Accepted]);

            $advance = $proposal->resolvedAdvanceMinor();
            $deposit = null;

            if ($advance > 0) {
                $deposit = $this->createDepositInvoice($proposal, $company, $advance);
                $proposal->update(['deposit_invoice_id' => $deposit->id]);
            } else {
                $remainder = $this->createFullInvoiceFromProposal($proposal, $company, InvoiceKind::Standard);
                $proposal->update(['remainder_invoice_id' => $remainder->id]);
            }

            return [
                'proposal' => $proposal->fresh(['depositInvoice', 'remainderInvoice']),
                'depositInvoice' => $deposit,
            ];
        });
    }

    public function createDepositInvoice(Proposal $proposal, Company $company, int $advanceMinor): Invoice
    {
        $taxes = $this->totalsService->fromSubtotalMinor($advanceMinor, $company);
        // Advance is treated as TTC target when possible; use advance as line HT approx of advance
        // Keep simple: single line = advance HT, taxes applied by company settings.
        $lineHt = $taxes['subtotal'];

        $invoice = Invoice::query()->withoutGlobalScope('tenant')->create([
            'company_id' => $company->id,
            'client_id' => $proposal->client_id,
            'proposal_id' => $proposal->id,
            'project_id' => $proposal->project_id,
            'number' => null,
            'status' => InvoiceStatus::Sent,
            'invoice_kind' => InvoiceKind::Deposit,
            'subtotal_amount' => $taxes['subtotal'],
            'vat_amount' => $taxes['vat'],
            'fiscal_stamp_amount' => $taxes['stamp'],
            'amount' => $taxes['total'],
            'currency' => $proposal->currency,
            'due_date' => now()->addDays(7),
            'customer_notes' => __('quote_deposit_invoice_note', ['quote' => $proposal->number ?? $proposal->name]),
        ]);

        $this->referenceService->assignNextNumber($invoice, $company);

        InvoiceItem::query()->withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'invoice_id' => $invoice->id,
            'description' => __('quote_deposit_line', ['quote' => $proposal->number ?? $proposal->name]),
            'quantity' => 1,
            'unit_amount' => $lineHt,
            'total_amount' => $lineHt,
        ]);

        return $invoice;
    }

    public function createFullInvoiceFromProposal(Proposal $proposal, Company $company, InvoiceKind $kind = InvoiceKind::Remainder): Invoice
    {
        $proposal->loadMissing('items');

        $taxes = [
            'subtotal' => (int) $proposal->subtotal_amount,
            'vat' => (int) $proposal->vat_amount,
            'stamp' => (int) $proposal->fiscal_stamp_amount,
            'total' => (int) $proposal->amount,
            'discount_type' => $proposal->discount_type,
            'discount_value' => $proposal->discount_value,
            'discount' => (int) ($proposal->discount_amount ?? 0),
        ];

        $scaled = false;
        if ($kind === InvoiceKind::Remainder && $proposal->deposit_invoice_id) {
            $deposit = Invoice::query()->withoutGlobalScopes()->find($proposal->deposit_invoice_id);
            $depositPaid = $deposit ? $deposit->completedPaymentsTotalMinor() : 0;
            $remainderTotal = max(0, (int) $proposal->amount - $depositPaid);
            if ($remainderTotal < (int) $proposal->amount && $remainderTotal > 0) {
                // Scale HT proportionally for remainder document
                $ratio = $remainderTotal / max(1, (int) $proposal->amount);
                $scaled = true;
                $taxes = [
                    'subtotal' => (int) round((int) $proposal->subtotal_amount * $ratio),
                    'vat' => (int) round((int) $proposal->vat_amount * $ratio),
                    'stamp' => 0,
                    'total' => $remainderTotal,
                    'discount_type' => null,
                    'discount_value' => null,
                    'discount' => 0,
                ];
            }
        }

        $invoice = Invoice::query()->withoutGlobalScope('tenant')->create([
            'company_id' => $company->id,
            'client_id' => $proposal->client_id,
            'proposal_id' => $proposal->id,
            'project_id' => $proposal->project_id,
            'number' => null,
            'status' => InvoiceStatus::Sent,
            'invoice_kind' => $kind,
            'subtotal_amount' => $taxes['subtotal'],
            'discount_type' => $taxes['discount_type'],
            'discount_value' => $taxes['discount_value'],
            'discount_amount' => $taxes['discount'],
            'vat_amount' => $taxes['vat'],
            'fiscal_stamp_amount' => $taxes['stamp'],
            'amount' => $taxes['total'],
            'currency' => $proposal->currency,
            'due_date' => now()->addDays(30),
            'customer_notes' => $proposal->customer_notes,
        ]);

        $this->referenceService->assignNextNumber($invoice, $company);

        if ($proposal->items->isNotEmpty()) {
            foreach ($proposal->items as $row) {
                InvoiceItem::query()->withoutGlobalScopes()->create([
                    'company_id' => $company->id,
                    'invoice_id' => $invoice->id,
                    'product_id' => $row->product_id,
                    'product_variant_id' => $row->product_variant_id,
                    'title' => $row->title,
                    'description' => $row->description,
                    'unit' => $row->unit,
                    'quantity' => $row->quantity,
                    'unit_amount' => $row->unit_amount,
                    'total_amount' => $row->total_amount,
                    // Ratio-scaled remainder: the stored totals no longer match the lines, keep the single-rate display.
                    'vat_rate' => $scaled ? null : $row->vat_rate,
                ]);
            }
        } else {
            InvoiceItem::query()->withoutGlobalScopes()->create([
                'company_id' => $company->id,
                'invoice_id' => $invoice->id,
                'description' => $proposal->name,
                'quantity' => 1,
                'unit_amount' => $taxes['subtotal'],
                'total_amount' => $taxes['subtotal'],
            ]);
        }

        return $invoice;
    }

    /**
     * When a deposit invoice is fully paid: generate receipt + remainder invoice + stock for products on full invoice.
     */
    public function handleDepositPaid(Invoice $invoice): void
    {
        if ($invoice->invoice_kind !== InvoiceKind::Deposit) {
            if ($invoice->invoice_kind === InvoiceKind::Remainder || $invoice->invoice_kind === InvoiceKind::Standard) {
                $this->decrementStockForPaidInvoice($invoice);
            }

            return;
        }

        $proposal = Proposal::query()->withoutGlobalScopes()
            ->where('deposit_invoice_id', $invoice->id)
            ->first();

        if (! $proposal) {
            return;
        }

        $payment = $invoice->payments()
            ->where('status', PaymentStatus::Completed)
            ->latest('paid_at')
            ->first();

        if ($payment) {
            try {
                $this->receipts->generateAndAttach($payment, $invoice);
            } catch (Throwable) {
                // Non-fatal: payment still succeeded.
            }
        }

        if ($proposal->remainder_invoice_id) {
            return;
        }

        $company = Company::query()->find($proposal->company_id);
        if (! $company) {
            return;
        }

        $remainder = $this->createFullInvoiceFromProposal($proposal, $company, InvoiceKind::Remainder);
        $proposal->update(['remainder_invoice_id' => $remainder->id]);
    }

    public function decrementStockForPaidInvoice(Invoice $invoice): void
    {
        $invoice->loadMissing(['items.product', 'items.productVariant', 'company']);
        $company = $invoice->company;
        if (! $company) {
            return;
        }

        foreach ($invoice->items as $item) {
            $product = $item->product;
            if (! $product instanceof Product || ! $product->track_stock || $product->isService()) {
                continue;
            }
            $variant = $item->productVariant ?? $product->variants()->first();
            if (! $variant) {
                continue;
            }
            try {
                $this->stock->adjust(
                    $company,
                    $variant,
                    -1 * max(1, (int) $item->quantity),
                    'invoice_paid',
                    Invoice::class,
                    (string) $invoice->id,
                );
            } catch (Throwable) {
                // Skip stock errors; invoice remains paid.
            }
        }
    }
}
