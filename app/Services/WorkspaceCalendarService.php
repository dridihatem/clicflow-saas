<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\Company;
use App\Models\InstalledModule;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;
use App\Models\ProjectInstallment;
use App\Models\ProjectTask;
use App\Models\Proposal;
use App\Models\User;
use App\Models\WorkspaceCalendarEvent;
use App\Services\CalendarMeetingLinkService;
use Illuminate\Support\Carbon;

class WorkspaceCalendarService
{
    /**
     * @return list<array{
     *   id: string,
     *   type: string,
     *   title: string,
     *   date: string,
     *   end_date: string|null,
     *   subtitle: string|null,
     *   url: string|null,
     *   color: string
     * }>
     */
    public function eventsForCompany(Company $company, Carbon $from, Carbon $to, ?string $clientId = null, bool $portal = false, ?User $user = null): array
    {
        $events = array_merge(
            $this->invoiceDueEvents($company, $from, $to, $clientId, $portal),
            $this->recurringInvoiceEvents($company, $from, $to, $clientId, $portal),
            $this->invoiceReminderSentEvents($company, $from, $to, $clientId, $portal),
            $this->installmentEvents($company, $from, $to, $clientId, $portal),
            $this->paymentReceivedEvents($company, $from, $to, $clientId, $portal),
            $this->projectEvents($company, $from, $to, $clientId, $portal, $user),
            $this->proposalEvents($company, $from, $to, $clientId, $portal),
            $this->projectTaskEvents($company, $from, $to, $clientId, $portal, $user),
            $this->customEvents($company, $from, $to, $clientId, $portal),
        );

        usort($events, fn (array $a, array $b) => [$a['date'], $a['title']] <=> [$b['date'], $b['title']]);

        return array_map(function (array $ev) use ($company, $portal) {
            if (! empty($ev['can_delete'])) {
                return $ev;
            }

            return $this->withSyncMeta($ev, $company, $portal);
        }, $events);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function invoiceDueEvents(Company $company, Carbon $from, Carbon $to, ?string $clientId, bool $portal): array
    {
        $events = [];

        $query = Invoice::query()
            ->where('company_id', $company->id)
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [$from->toDateString(), $to->toDateString()])
            ->where('status', '!=', InvoiceStatus::Cancelled)
            ->with('client:id,name');

        if ($clientId !== null) {
            $query->where('client_id', $clientId);
        }

        foreach ($query->get() as $invoice) {
            $currency = flowdesk_invoice_currency($invoice);
            $amount = flowdesk_format_minor((int) $invoice->amount, $currency).' '.$currency;
            $label = $invoice->number ?: __('Invoice');
            $isPaid = $invoice->status === InvoiceStatus::Paid;

            $events[] = [
                'id' => 'invoice-due-'.$invoice->id,
                'type' => 'invoice',
                'title' => $isPaid
                    ? __('calendar_event_invoice_paid', ['number' => $label])
                    : __('calendar_event_invoice_due', ['number' => $label]),
                'date' => $invoice->due_date->toDateString(),
                'end_date' => null,
                'subtitle' => trim(($invoice->client?->name ?? '').' · '.$amount.' · '.$invoice->status->label(), ' ·'),
                'url' => $portal
                    ? route('portal.invoices.show', $invoice)
                    : route('invoices.show', $invoice),
                'color' => $isPaid ? 'emerald' : 'rose',
                'client_id' => $invoice->client_id,
                'client_name' => $invoice->client?->name,
                'reference' => $label,
                'amount' => $amount,
                'status' => $invoice->status->label(),
                'entity_type' => 'invoice',
                'entity_id' => $invoice->id,
            ];
        }

        return $events;
    }

    /**
     * Upcoming occurrences for active recurring invoices (not yet generated).
     *
     * @return list<array<string, mixed>>
     */
    private function recurringInvoiceEvents(Company $company, Carbon $from, Carbon $to, ?string $clientId, bool $portal): array
    {
        $events = [];
        $recurring = app(RecurringInvoiceService::class);

        $query = Invoice::query()
            ->where('company_id', $company->id)
            ->where('recurrence_active', true)
            ->whereNull('recurrence_parent_id')
            ->whereNotNull('recurrence_next_on')
            ->where('status', '!=', InvoiceStatus::Cancelled)
            ->with('client:id,name');

        if ($clientId !== null) {
            $query->where('client_id', $clientId);
        }

        foreach ($query->get() as $invoice) {
            $currency = flowdesk_invoice_currency($invoice);
            $amount = flowdesk_format_minor((int) $invoice->amount, $currency).' '.$currency;
            $label = $invoice->number ?: __('Invoice');
            $interval = $invoice->recurrenceLabel() ?? __('invoice_recurrence.badge');

            foreach ($recurring->upcomingDates($invoice, $from, $to) as $date) {
                $events[] = [
                    'id' => 'invoice-recurring-'.$invoice->id.'-'.$date,
                    'type' => 'invoice',
                    'title' => __('calendar_event_invoice_recurring', ['number' => $label]),
                    'date' => $date,
                    'end_date' => null,
                    'subtitle' => trim(($invoice->client?->name ?? '').' · '.$amount.' · '.$interval, ' ·'),
                    'url' => $portal
                        ? route('portal.invoices.show', $invoice)
                        : route('invoices.show', $invoice),
                    'color' => 'violet',
                    'client_id' => $invoice->client_id,
                    'client_name' => $invoice->client?->name,
                    'reference' => $label,
                    'amount' => $amount,
                    'status' => $interval,
                    'entity_type' => 'invoice',
                    'entity_id' => $invoice->id,
                ];
            }
        }

        return $events;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function invoiceReminderSentEvents(Company $company, Carbon $from, Carbon $to, ?string $clientId, bool $portal): array
    {
        $events = [];

        $query = Invoice::query()
            ->where('company_id', $company->id)
            ->whereNotNull('reminder_sent_at')
            ->whereBetween('reminder_sent_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->where('status', '!=', InvoiceStatus::Cancelled)
            ->with('client:id,name');

        if ($clientId !== null) {
            $query->where('client_id', $clientId);
        }

        foreach ($query->get() as $invoice) {
            $label = $invoice->number ?: __('Invoice');

            $events[] = [
                'id' => 'invoice-reminder-sent-'.$invoice->id,
                'type' => 'reminder',
                'title' => __('calendar_event_invoice_reminder_sent', ['number' => $label]),
                'date' => $invoice->reminder_sent_at->toDateString(),
                'end_date' => null,
                'subtitle' => $invoice->client?->name,
                'url' => $portal
                    ? route('portal.invoices.show', $invoice)
                    : route('invoices.show', $invoice),
                'color' => 'amber',
                'client_id' => $invoice->client_id,
                'client_name' => $invoice->client?->name,
                'reference' => $label,
                'status' => $invoice->status->label(),
                'entity_type' => 'invoice',
                'entity_id' => $invoice->id,
            ];
        }

        return $events;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function installmentEvents(Company $company, Carbon $from, Carbon $to, ?string $clientId, bool $portal): array
    {
        $events = [];

        $query = ProjectInstallment::query()
            ->whereBetween('due_date', [$from->toDateString(), $to->toDateString()])
            ->whereHas('project', function ($q) use ($company, $clientId) {
                $q->where('company_id', $company->id);
                if ($clientId !== null) {
                    $q->where('client_id', $clientId);
                }
            })
            ->with(['project:id,title,client_id,company_id', 'project.client:id,name', 'project.company:id,default_currency']);

        foreach ($query->get() as $installment) {
            $project = $installment->project;
            if (! $project) {
                continue;
            }

            $currency = flowdesk_normalize_currency_code($project->company?->default_currency);
            $amount = flowdesk_format_minor((int) $installment->amount_minor, $currency).' '.$currency;

            $events[] = [
                'id' => 'installment-'.$installment->id,
                'type' => 'payment_due',
                'title' => __('calendar_event_installment_due', ['project' => $project->title]),
                'date' => $installment->due_date->toDateString(),
                'end_date' => null,
                'subtitle' => trim(($project->client?->name ?? '').' · '.$amount, ' ·'),
                'url' => $portal
                    ? route('portal.projects.show', $project)
                    : route('projects.show', $project),
                'color' => 'rose',
                'client_id' => $project->client_id,
                'client_name' => $project->client?->name,
                'reference' => $project->title,
                'amount' => $amount,
                'entity_type' => 'project',
                'entity_id' => $project->id,
            ];
        }

        return $events;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function paymentReceivedEvents(Company $company, Carbon $from, Carbon $to, ?string $clientId, bool $portal): array
    {
        $events = [];

        $query = Payment::query()
            ->where('company_id', $company->id)
            ->where('status', PaymentStatus::Completed)
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->with(['invoice:id,number,client_id,currency', 'invoice.client:id,name']);

        if ($clientId !== null) {
            $query->whereHas('invoice', fn ($q) => $q->where('client_id', $clientId));
        }

        foreach ($query->get() as $payment) {
            $invoice = $payment->invoice;
            $currency = $invoice ? flowdesk_invoice_currency($invoice) : flowdesk_normalize_currency_code(null);
            $amount = flowdesk_format_minor((int) $payment->amount, $currency).' '.$currency;
            $label = $invoice?->number ?: __('Payment');

            $events[] = [
                'id' => 'payment-'.$payment->id,
                'type' => 'payment_received',
                'title' => __('calendar_event_payment_received', ['reference' => $label]),
                'date' => $payment->paid_at->toDateString(),
                'end_date' => null,
                'subtitle' => trim(($invoice?->client?->name ?? '').' · '.$amount, ' ·'),
                'url' => $portal
                    ? ($invoice ? route('portal.invoices.show', $invoice) : route('portal.payments.index'))
                    : ($invoice ? route('invoices.show', $invoice) : null),
                'color' => 'emerald',
                'client_id' => $invoice?->client_id,
                'client_name' => $invoice?->client?->name,
                'reference' => $label,
                'amount' => $amount,
                'status' => PaymentStatus::Completed->label(),
                'entity_type' => 'invoice',
                'entity_id' => $invoice?->id,
            ];
        }

        return $events;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function projectEvents(Company $company, Carbon $from, Carbon $to, ?string $clientId, bool $portal, ?User $user = null): array
    {
        $events = [];

        $createdQuery = Project::query()
            ->where('company_id', $company->id)
            ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->with('client:id,name');

        if ($clientId !== null) {
            $createdQuery->where('client_id', $clientId);
        }

        if (! $portal && $user instanceof User) {
            $createdQuery->accessibleBy($user);
        }

        foreach ($createdQuery->get() as $project) {
            $events[] = [
                'id' => 'project-created-'.$project->id,
                'type' => 'project',
                'title' => __('calendar_event_project_created', ['project' => $project->title]),
                'date' => $project->created_at->toDateString(),
                'end_date' => null,
                'subtitle' => $project->client?->name,
                'url' => $portal
                    ? route('portal.projects.show', $project)
                    : route('projects.show', $project),
                'color' => 'cyan',
                'client_id' => $project->client_id,
                'client_name' => $project->client?->name,
                'reference' => $project->title,
                'status' => $project->status instanceof \BackedEnum
                    ? $project->status->value
                    : (string) ($project->status ?? ''),
                'entity_type' => 'project',
                'entity_id' => $project->id,
            ];
        }

        $deadlineQuery = Project::query()
            ->where('company_id', $company->id)
            ->whereNotNull('final_deadline')
            ->whereBetween('final_deadline', [$from->toDateString(), $to->toDateString()])
            ->with('client:id,name');

        if ($clientId !== null) {
            $deadlineQuery->where('client_id', $clientId);
        }

        if (! $portal && $user instanceof User) {
            $deadlineQuery->accessibleBy($user);
        }

        foreach ($deadlineQuery->get() as $project) {
            $events[] = [
                'id' => 'project-deadline-'.$project->id,
                'type' => 'project',
                'title' => __('calendar_event_project_deadline', ['project' => $project->title]),
                'date' => $project->final_deadline->toDateString(),
                'end_date' => null,
                'subtitle' => $project->client?->name,
                'url' => $portal
                    ? route('portal.projects.show', $project)
                    : route('projects.show', $project),
                'color' => 'violet',
                'client_id' => $project->client_id,
                'client_name' => $project->client?->name,
                'reference' => $project->title,
                'status' => $project->status instanceof \BackedEnum
                    ? $project->status->value
                    : (string) ($project->status ?? ''),
                'entity_type' => 'project',
                'entity_id' => $project->id,
            ];
        }

        return $events;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function proposalEvents(Company $company, Carbon $from, Carbon $to, ?string $clientId, bool $portal): array
    {
        $events = [];

        $query = Proposal::query()
            ->where('company_id', $company->id)
            ->whereNotNull('valid_until')
            ->whereBetween('valid_until', [$from->toDateString(), $to->toDateString()])
            ->with('client:id,name');

        if ($clientId !== null) {
            $query->where('client_id', $clientId);
        }

        foreach ($query->get() as $proposal) {
            $currency = flowdesk_normalize_currency_code($proposal->currency);
            $amount = flowdesk_format_minor((int) $proposal->amount, $currency).' '.$currency;
            $name = $proposal->name ?: __('Proposal');

            $events[] = [
                'id' => 'proposal-'.$proposal->id,
                'type' => 'proposal',
                'title' => __('calendar_event_proposal_expires', ['proposal' => $name]),
                'date' => $proposal->valid_until->toDateString(),
                'end_date' => null,
                'subtitle' => trim(($proposal->client?->name ?? '').' · '.$amount, ' ·'),
                'url' => $portal ? route('portal.proposals.show', $proposal) : route('proposals.show', $proposal),
                'color' => 'fuchsia',
                'client_id' => $proposal->client_id,
                'client_name' => $proposal->client?->name,
                'reference' => $name,
                'amount' => $amount,
                'status' => $proposal->status?->label() ?? (string) ($proposal->status?->value ?? ''),
                'entity_type' => 'proposal',
                'entity_id' => $proposal->id,
            ];
        }

        return $events;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function projectTaskEvents(Company $company, Carbon $from, Carbon $to, ?string $clientId, bool $portal, ?User $user = null): array
    {
        $events = [];

        $query = ProjectTask::query()
            ->where('company_id', $company->id)
            ->where(function ($q) use ($from, $to) {
                $q->where(function ($inner) use ($from, $to) {
                    $inner->whereNotNull('starts_on')
                        ->whereBetween('starts_on', [$from->toDateString(), $to->toDateString()]);
                })->orWhere(function ($inner) use ($from, $to) {
                    $inner->whereNull('starts_on')
                        ->whereNotNull('ends_on')
                        ->whereBetween('ends_on', [$from->toDateString(), $to->toDateString()]);
                })->orWhere(function ($inner) use ($from, $to) {
                    $inner->whereNotNull('starts_on')
                        ->whereNotNull('ends_on')
                        ->where('starts_on', '<=', $to->toDateString())
                        ->where('ends_on', '>=', $from->toDateString());
                });
            })
            ->with(['project:id,title,client_id', 'project.client:id,name']);

        if ($clientId !== null) {
            $query->whereHas('project', fn ($q) => $q->where('client_id', $clientId));
        }

        if (! $portal && $user instanceof User) {
            $query->whereHas('project', fn ($q) => $q->accessibleBy($user));
        }

        foreach ($query->get() as $task) {
            $start = $task->starts_on ?? $task->ends_on;
            if ($start === null) {
                continue;
            }

            $end = $task->ends_on;
            if ($end !== null && $end->lt($start)) {
                $end = $start;
            }

            $events[] = [
                'id' => 'task-'.$task->id,
                'type' => 'meeting',
                'title' => $task->title,
                'date' => $start->toDateString(),
                'end_date' => $end?->toDateString(),
                'subtitle' => trim(($task->project?->title ?? '').($task->project?->client?->name ? ' · '.$task->project->client->name : ''), ' ·'),
                'url' => $task->project
                    ? ($portal
                        ? route('portal.projects.show', $task->project)
                        : route('projects.show', $task->project))
                    : null,
                'color' => 'indigo',
                'client_id' => $task->project?->client_id,
                'client_name' => $task->project?->client?->name,
                'reference' => $task->project?->title,
                'status' => $task->status instanceof \BackedEnum
                    ? $task->status->value
                    : (string) ($task->status ?? ''),
                'description' => $task->description ? mb_substr(strip_tags((string) $task->description), 0, 240) : null,
                'entity_type' => 'project',
                'entity_id' => $task->project_id,
            ];
        }

        return $events;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function customEvents(Company $company, Carbon $from, Carbon $to, ?string $clientId, bool $portal): array
    {
        // Portal: only show custom events attached to this client (meetings they booked).
        if ($portal && $clientId === null) {
            return [];
        }

        $events = [];

        $query = WorkspaceCalendarEvent::query()
            ->where('company_id', $company->id)
            ->with('client:id,name')
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('starts_on', [$from->toDateString(), $to->toDateString()])
                    ->orWhere(function ($inner) use ($from, $to) {
                        $inner->whereNotNull('ends_on')
                            ->where('starts_on', '<=', $to->toDateString())
                            ->where('ends_on', '>=', $from->toDateString());
                    });
            });

        if ($clientId !== null) {
            $query->where('client_id', $clientId);
        }

        foreach ($query->get() as $event) {
            $type = $event->calendarType();
            $url = $this->calendarEventUrl($event, $portal);
            $meetingUrl = app(CalendarMeetingLinkService::class)->publicMeetingUrl($event);
            $description = $event->description ? mb_substr(strip_tags((string) $event->description), 0, 240) : null;
            $events[] = [
                'id' => 'custom-'.$event->id,
                'type' => $type,
                'title' => $event->title,
                'date' => $event->starts_on->toDateString(),
                'end_date' => $event->ends_on?->toDateString(),
                'subtitle' => $description,
                'url' => $meetingUrl ?? $url,
                'meeting_url' => $meetingUrl,
                'meeting_link_type' => $event->meeting_link_type?->value ?? (string) $event->meeting_link_type,
                'color' => $type === 'meeting' ? 'indigo' : 'violet',
                'can_delete' => ! $portal,
                'google_synced' => filled($event->google_calendar_event_id),
                'sync_kind' => 'custom',
                'client_id' => $event->client_id,
                'client_name' => $event->client?->name,
                'description' => $description,
                'reference' => $event->title,
                'entity_type' => 'event',
                'entity_id' => $event->id,
            ];
        }

        return $events;
    }

    /**
     * @param  array<string, mixed>  $event
     * @return array<string, mixed>
     */
    private function withSyncMeta(array $event, Company $company, bool $portal): array
    {
        if ($portal) {
            return array_merge($event, [
                'can_delete' => false,
                'google_synced' => false,
                'sync_kind' => null,
            ]);
        }

        $id = (string) ($event['id'] ?? '');
        $syncKind = 'generic';
        $googleSynced = false;

        if (str_starts_with($id, 'project-')) {
            $syncKind = 'project';
            $projectId = preg_replace('/^project-(?:created|deadline)-/', '', $id);
            if (is_string($projectId) && $projectId !== '') {
                $googleSynced = Project::query()
                    ->where('company_id', $company->id)
                    ->whereKey($projectId)
                    ->whereNotNull('google_calendar_event_id')
                    ->exists();
            }
        }

        return array_merge($event, [
            'can_delete' => false,
            'google_synced' => $googleSynced,
            'sync_kind' => $syncKind,
        ]);
    }

    /**
     * Compact data for top bar mini calendar and dashboard widget.
     *
     * @return array{
     *   month: string,
     *   today: string,
     *   dayCounts: array<string, int>,
     *   upcoming: list<array<string, mixed>>,
     *   calendarUrl: string
     * }|null
     */
    public function navPreview(Company $company, ?string $clientId = null, bool $portal = false, ?string $month = null, ?User $user = null): array
    {
        $today = Carbon::today();
        $viewMonth = is_string($month) && preg_match('/^\d{4}-\d{2}$/', $month)
            ? Carbon::createFromFormat('Y-m', $month)->startOfMonth()
            : $today->copy()->startOfMonth();

        $monthStart = $viewMonth->copy();
        $monthEnd = $viewMonth->copy()->endOfMonth();
        $horizonEnd = $today->copy()->addDays(45);
        $todayIso = $today->toDateString();

        $gridEvents = $this->eventsForCompany($company, $monthStart, $monthEnd, $clientId, $portal, $user);
        $upcomingEvents = $this->eventsForCompany($company, $today, $horizonEnd, $clientId, $portal, $user);

        $upcoming = array_values(array_filter(
            $upcomingEvents,
            fn (array $ev) => ($ev['end_date'] ?? $ev['date']) >= $todayIso,
        ));
        usort($upcoming, fn (array $a, array $b) => [$a['date'], $a['title']] <=> [$b['date'], $b['title']]);
        $upcoming = array_slice($upcoming, 0, 5);

        $dayCounts = [];
        for ($day = 1; $day <= $monthEnd->day; $day++) {
            $iso = $monthStart->copy()->day($day)->toDateString();
            $count = count(array_filter($gridEvents, fn (array $ev) => $this->eventOnDate($ev, $iso)));
            if ($count > 0) {
                $dayCounts[$iso] = $count;
            }
        }

        return [
            'month' => $viewMonth->format('Y-m'),
            'today' => $todayIso,
            'dayCounts' => $dayCounts,
            'upcoming' => array_map(fn (array $ev) => [
                'id' => $ev['id'],
                'title' => $ev['title'],
                'date' => $ev['date'],
                'type' => $ev['type'],
                'color' => $ev['color'],
                'url' => $ev['url'] ?? null,
            ], $upcoming),
            'calendarUrl' => $portal ? route('portal.calendar') : route('calendar.index'),
            'previewUrl' => $portal ? route('portal.calendar.preview') : route('calendar.preview'),
        ];
    }

    private function calendarEventUrl(WorkspaceCalendarEvent $event, bool $portal): ?string
    {
        $meeting = app(CalendarMeetingLinkService::class)->publicMeetingUrl($event);
        if ($meeting !== null) {
            return $meeting;
        }

        if ($portal || $event->source_type !== 'module_property_viewing') {
            return null;
        }

        $module = InstalledModule::query()
            ->where('company_id', $event->company_id)
            ->where('is_enabled', true)
            ->where(function ($q) {
                $q->where('slug', 'qatar-real-estate')
                    ->orWhere('slug', 'qatar-property-viewings');
            })
            ->first();

        if (! $module) {
            return null;
        }

        return route('modules.show', ['slug' => $module->slug, 'page' => 'viewings']);
    }

    private function eventOnDate(array $ev, string $iso): bool
    {
        $start = $ev['date'];
        $end = $ev['end_date'] ?? $ev['date'];

        return $iso >= $start && $iso <= $end;
    }
}
