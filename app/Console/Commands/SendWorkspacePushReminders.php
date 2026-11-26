<?php

namespace App\Console\Commands;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\WorkspaceCalendarEvent;
use App\Services\FcmPushService;
use Illuminate\Console\Command;

class SendWorkspacePushReminders extends Command
{
    protected $signature = 'clicflow:send-push-reminders';

    protected $description = 'Push FCM/Expo alerts for upcoming calendar events and invoice due dates.';

    public function handle(FcmPushService $push): int
    {
        $events = 0;
        $invoices = 0;

        $today = now()->toDateString();
        $horizon = now()->addDay()->toDateString();

        $upcomingEvents = WorkspaceCalendarEvent::query()
            ->withoutGlobalScopes()
            ->whereNull('push_notified_at')
            ->whereDate('starts_on', '>=', $today)
            ->whereDate('starts_on', '<=', $horizon)
            ->whereIn('kind', ['meeting', 'reminder', 'appointment'])
            ->orderBy('starts_on')
            ->limit(500)
            ->get();

        foreach ($upcomingEvents as $event) {
            $title = match ($event->kind) {
                'reminder' => __('Reminder'),
                'appointment' => __('Appointment'),
                default => __('Upcoming meeting'),
            };
            $when = $event->starts_on?->format('Y-m-d') ?? $today;
            if (! empty($event->start_time)) {
                $when .= ' '.substr((string) $event->start_time, 0, 5);
            }

            $result = $push->sendToCompany(
                (string) $event->company_id,
                $title,
                trim($event->title).' · '.$when,
                [
                    'type' => 'calendar_event',
                    'event_id' => (string) $event->id,
                    'kind' => (string) $event->kind,
                ],
            );

            if ($result['sent'] > 0 || $result['failed'] === 0) {
                $event->forceFill(['push_notified_at' => now()])->saveQuietly();
                $events++;
            }
        }

        $dueInvoices = Invoice::query()
            ->withoutGlobalScope('tenant')
            ->whereIn('status', [InvoiceStatus::Sent, InvoiceStatus::Overdue, InvoiceStatus::Draft])
            ->whereNotNull('due_date')
            ->whereNull('staff_due_push_sent_at')
            ->whereDate('due_date', '>=', $today)
            ->whereDate('due_date', '<=', $horizon)
            ->with(['client'])
            ->limit(500)
            ->get();

        foreach ($dueInvoices as $invoice) {
            if (in_array($invoice->status, [InvoiceStatus::Paid, InvoiceStatus::Cancelled], true)) {
                continue;
            }

            $clientName = $invoice->client?->name ?? __('Client');
            $ref = $invoice->number ?? $invoice->reference ?? (string) $invoice->id;
            $due = $invoice->due_date?->format('Y-m-d') ?? $today;

            $result = $push->sendToCompany(
                (string) $invoice->company_id,
                __('Invoice due soon'),
                "{$clientName} · {$ref} · {$due}",
                [
                    'type' => 'invoice_due',
                    'invoice_id' => (string) $invoice->id,
                ],
            );

            if ($result['sent'] > 0 || $result['failed'] === 0) {
                $invoice->forceFill(['staff_due_push_sent_at' => now()])->saveQuietly();
                $invoices++;
            }
        }

        $this->info("Push reminders: {$events} events, {$invoices} invoices.");

        return self::SUCCESS;
    }
}
