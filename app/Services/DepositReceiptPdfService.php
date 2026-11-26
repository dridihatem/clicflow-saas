<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class DepositReceiptPdfService
{
    public function generateAndAttach(Payment $payment, Invoice $invoice): string
    {
        $invoice->loadMissing(['client', 'company', 'proposal']);
        $payment->loadMissing(['company']);

        $html = view('payments.receipt-pdf', [
            'payment' => $payment,
            'invoice' => $invoice,
        ])->render();

        $html = app(DomPdfArabicFixer::class)->reshapeHtml($html);
        $binary = Pdf::loadHTML($html)->output();

        $path = 'payment-receipts/'.$invoice->company_id.'/'.$payment->id.'.pdf';
        Storage::disk('local')->put($path, $binary);

        $payment->forceFill([
            'receipt_path' => $path,
            'payment_kind' => $payment->payment_kind ?? \App\Enums\PaymentEntryKind::Deposit,
        ])->save();

        return $path;
    }
}
