<?php

namespace App\Console\Commands;

use App\Enums\InvoiceStatus;
use App\Jobs\SendInvoiceReminderJob;
use App\Models\Invoice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendInvoiceReminders extends Command
{
    protected $signature = 'invoices:send-reminders';

    protected $description = 'Queue email reminders for overdue unpaid invoices (one per invoice). Each invoice is marked reminded only after its email was sent.';

    public function handle(): int
    {
        $q = Invoice::query()->withoutGlobalScope('tenant')
            ->whereIn('status', [InvoiceStatus::Sent, InvoiceStatus::Overdue])
            ->whereNotNull('due_date')
            ->where('due_date', '<', now()->startOfDay())
            ->whereNull('reminder_sent_at')
            ->with(['client', 'company']);

        /** @var array<string, array{queued:int, skipped_no_email:int}> $perTenant */
        $perTenant = [];

        foreach ($q->cursor() as $invoice) {
            if ($invoice->company === null) {
                continue;
            }
            $cid = (string) $invoice->company_id;
            $perTenant[$cid] ??= ['queued' => 0, 'skipped_no_email' => 0];

            $email = trim((string) ($invoice->client?->email ?? ''));
            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $perTenant[$cid]['skipped_no_email']++;

                continue;
            }

            SendInvoiceReminderJob::dispatch((string) $invoice->id);
            $perTenant[$cid]['queued']++;
        }

        foreach ($perTenant as $cid => $counts) {
            if ($counts['skipped_no_email'] > 0) {
                Log::warning('invoice_reminders_skipped_no_client_email', ['company_id' => $cid, 'count' => $counts['skipped_no_email']]);
            }
            $this->line("Company {$cid}: {$counts['queued']} queued, {$counts['skipped_no_email']} skipped (no client email)");
        }

        return self::SUCCESS;
    }
}
