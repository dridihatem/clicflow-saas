<?php

namespace App\Jobs;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Services\FcmPushService;
use App\Services\InvoiceMailService;
use App\Services\TenantSmtpMailer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends one overdue-invoice reminder. The invoice is marked as reminded
 * (reminder_sent_at + status Overdue) only after the email was actually sent.
 * Failures retry with backoff, then land in failed_jobs with a per-tenant warning.
 */
class SendInvoiceReminderJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    /** Prevent a second dispatch while one is pending/retrying (seconds). */
    public int $uniqueFor = 72000;

    public function __construct(public string $invoiceId) {}

    public function uniqueId(): string
    {
        return $this->invoiceId;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public static function eligible(?Invoice $invoice): bool
    {
        return $invoice !== null
            && $invoice->reminder_sent_at === null
            && in_array($invoice->status, [InvoiceStatus::Sent, InvoiceStatus::Overdue], true)
            && $invoice->due_date !== null
            && $invoice->due_date->lt(now()->startOfDay());
    }

    public function handle(InvoiceMailService $mailer, FcmPushService $push): void
    {
        $invoice = Invoice::query()->withoutGlobalScope('tenant')
            ->with(['client', 'company'])
            ->find($this->invoiceId);

        if (! self::eligible($invoice) || $invoice->company === null) {
            return; // paid, cancelled, already reminded, or deleted meanwhile
        }

        $company = $invoice->company;

        // Throws on failure → retried by the queue; nothing is marked.
        TenantSmtpMailer::synchronously(fn () => $mailer->send($invoice, $company, true));

        $invoice->update([
            'reminder_sent_at' => now(),
            'status' => $invoice->status === InvoiceStatus::Sent ? InvoiceStatus::Overdue : $invoice->status,
        ]);

        try {
            $clientName = $invoice->client?->name ?? __('Client');
            $ref = $invoice->number ?? $invoice->reference ?? (string) $invoice->id;
            $push->sendToCompany(
                (string) $company->id,
                __('Overdue invoice'),
                "{$clientName} · {$ref}",
                ['type' => 'invoice_overdue', 'invoice_id' => (string) $invoice->id],
            );
        } catch (Throwable $e) {
            Log::info('invoice_reminder_push_failed', ['invoice_id' => $invoice->id, 'message' => $e->getMessage()]);
        }
    }

    public function failed(?Throwable $e): void
    {
        $companyId = Invoice::query()->withoutGlobalScope('tenant')->whereKey($this->invoiceId)->value('company_id');
        Log::warning('invoice_reminder_failed', [
            'company_id' => $companyId,
            'invoice_id' => $this->invoiceId,
            'message' => $e?->getMessage(),
        ]);
    }
}
