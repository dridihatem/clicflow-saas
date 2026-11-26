<?php

namespace App\Services;

use App\Enums\InvoiceKind;
use App\Enums\InvoiceRecurrenceInterval;
use App\Enums\InvoiceStatus;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RecurringInvoiceService
{
    public function __construct(
        private InvoiceReferenceService $references,
        private InvoiceMailService $mailer,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function validationRules(): array
    {
        return [
            'recurrence_active' => ['sometimes', 'boolean'],
            'recurrence_interval' => ['nullable', Rule::enum(InvoiceRecurrenceInterval::class)],
            'recurrence_every_months' => ['nullable', 'integer', 'min:1', 'max:36'],
            'recurrence_ends_on' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function attributesFromRequest(Request $request, ?string $dueDate, bool $isChild = false): array
    {
        if ($isChild) {
            return [];
        }

        if (! $request->boolean('recurrence_active')) {
            return [
                'recurrence_interval' => null,
                'recurrence_every_months' => null,
                'recurrence_next_on' => null,
                'recurrence_ends_on' => null,
                'recurrence_active' => false,
            ];
        }

        $interval = InvoiceRecurrenceInterval::tryFrom((string) $request->input('recurrence_interval'));
        if ($interval === null) {
            throw ValidationException::withMessages([
                'recurrence_interval' => __('invoice_recurrence.interval_required'),
            ]);
        }

        $customMonths = $request->filled('recurrence_every_months')
            ? (int) $request->input('recurrence_every_months')
            : null;

        if ($interval === InvoiceRecurrenceInterval::Custom && ($customMonths === null || $customMonths < 1)) {
            throw ValidationException::withMessages([
                'recurrence_every_months' => __('invoice_recurrence.custom_months_required'),
            ]);
        }

        $months = $interval->months($customMonths);
        $anchor = $dueDate ? Carbon::parse($dueDate)->startOfDay() : Carbon::today();
        $nextOn = $this->addMonths($anchor, $months);
        $endsOn = $request->filled('recurrence_ends_on')
            ? Carbon::parse((string) $request->input('recurrence_ends_on'))->startOfDay()
            : null;

        return [
            'recurrence_interval' => $interval,
            'recurrence_every_months' => $months,
            'recurrence_next_on' => $nextOn->toDateString(),
            'recurrence_ends_on' => $endsOn?->toDateString(),
            'recurrence_active' => true,
        ];
    }

    /**
     * When editing an existing series, keep next_on after the latest generated child.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function attributesForExisting(Invoice $invoice, array $attributes): array
    {
        if (! ($attributes['recurrence_active'] ?? false) || $invoice->recurrence_parent_id) {
            return $attributes;
        }

        $months = max(1, (int) ($attributes['recurrence_every_months'] ?? 1));
        $lastChild = Invoice::query()->withoutGlobalScope('tenant')
            ->where('recurrence_parent_id', $invoice->id)
            ->whereNotNull('due_date')
            ->orderByDesc('due_date')
            ->first();

        if ($lastChild?->due_date) {
            $attributes['recurrence_next_on'] = $this->addMonths($lastChild->due_date->copy()->startOfDay(), $months)->toDateString();
        }

        $endsOn = isset($attributes['recurrence_ends_on']) && $attributes['recurrence_ends_on']
            ? Carbon::parse((string) $attributes['recurrence_ends_on'])->startOfDay()
            : null;
        $nextOn = isset($attributes['recurrence_next_on'])
            ? Carbon::parse((string) $attributes['recurrence_next_on'])->startOfDay()
            : null;

        if ($endsOn && $nextOn && $nextOn->gt($endsOn)) {
            $attributes['recurrence_active'] = false;
            $attributes['recurrence_next_on'] = null;
        }

        return $attributes;
    }

    public function addMonths(Carbon $date, int $months): Carbon
    {
        return $date->copy()->addMonthsNoOverflow(max(1, $months));
    }

    /**
     * @return list<string>
     */
    public function upcomingDates(Invoice $schedule, Carbon $from, Carbon $to, int $max = 24): array
    {
        if (! $schedule->recurrence_active || $schedule->recurrence_next_on === null) {
            return [];
        }

        $months = max(1, (int) ($schedule->recurrence_every_months ?? 1));
        $dates = [];
        $cursor = $schedule->recurrence_next_on->copy()->startOfDay();
        $ends = $schedule->recurrence_ends_on?->copy()->startOfDay();
        $guard = 0;

        while ($cursor->lte($to->copy()->startOfDay()) && $guard < $max) {
            if ($ends && $cursor->gt($ends)) {
                break;
            }
            if ($cursor->gte($from->copy()->startOfDay())) {
                $dates[] = $cursor->toDateString();
            }
            $cursor = $this->addMonths($cursor, $months);
            $guard++;
        }

        return $dates;
    }

    public function processDue(?Carbon $asOf = null): int
    {
        $asOf = ($asOf ?? Carbon::today())->copy()->startOfDay();
        $generated = 0;

        $schedules = Invoice::query()->withoutGlobalScope('tenant')
            ->where('recurrence_active', true)
            ->whereNull('recurrence_parent_id')
            ->whereNotNull('recurrence_next_on')
            ->whereDate('recurrence_next_on', '<=', $asOf->toDateString())
            ->where('status', '!=', InvoiceStatus::Cancelled)
            ->with(['items', 'client', 'company'])
            ->orderBy('recurrence_next_on')
            ->get();

        foreach ($schedules as $schedule) {
            $generated += $this->generateDueOccurrences($schedule, $asOf);
        }

        return $generated;
    }

    public function generateDueOccurrences(Invoice $schedule, Carbon $asOf): int
    {
        $created = 0;
        $guard = 0;

        while (
            $schedule->recurrence_active
            && $schedule->recurrence_next_on
            && $schedule->recurrence_next_on->lte($asOf)
            && $guard < 24
        ) {
            $guard++;
            $child = $this->generateNextOccurrence($schedule);
            if ($child !== null) {
                $created++;
            }
            $schedule->refresh();
        }

        return $created;
    }

    public function generateNextOccurrence(Invoice $schedule): ?Invoice
    {
        if (! $schedule->recurrence_active || $schedule->recurrence_next_on === null) {
            return null;
        }

        $company = $schedule->company;
        if ($company === null) {
            return null;
        }

        $due = $schedule->recurrence_next_on->copy()->startOfDay();
        $ends = $schedule->recurrence_ends_on?->copy()->startOfDay();
        if ($ends && $due->gt($ends)) {
            $schedule->update([
                'recurrence_active' => false,
                'recurrence_next_on' => null,
            ]);

            return null;
        }

        $months = max(1, (int) ($schedule->recurrence_every_months ?? 1));
        $nextOn = $this->addMonths($due, $months);
        $stillActive = ! $ends || $nextOn->lte($ends);

        $child = DB::transaction(function () use ($schedule, $company, $due, $nextOn, $stillActive): ?Invoice {
            $exists = Invoice::query()->withoutGlobalScope('tenant')
                ->where('recurrence_parent_id', $schedule->id)
                ->whereDate('due_date', $due->toDateString())
                ->exists();

            $schedule->update([
                'recurrence_next_on' => $stillActive ? $nextOn->toDateString() : null,
                'recurrence_active' => $stillActive,
            ]);

            if ($exists) {
                return null;
            }

            $invoice = Invoice::query()->withoutGlobalScope('tenant')->create([
                'company_id' => $company->id,
                'client_id' => $schedule->client_id,
                'proposal_id' => null,
                'project_id' => $schedule->project_id,
                'number' => null,
                'status' => InvoiceStatus::Sent,
                'invoice_kind' => $schedule->invoice_kind ?? InvoiceKind::Standard,
                'subtotal_amount' => $schedule->subtotal_amount,
                'discount_type' => $schedule->discount_type,
                'discount_value' => $schedule->discount_value,
                'discount_amount' => (int) ($schedule->discount_amount ?? 0),
                'vat_amount' => $schedule->vat_amount,
                'fiscal_stamp_amount' => $schedule->fiscal_stamp_amount,
                'amount' => $schedule->amount,
                'currency' => $schedule->currency,
                'due_date' => $due->toDateString(),
                'internal_notes' => $schedule->internal_notes,
                'customer_notes' => $schedule->customer_notes,
                'recurrence_parent_id' => $schedule->id,
                'recurrence_active' => false,
                'recurrence_interval' => null,
                'recurrence_every_months' => null,
                'recurrence_next_on' => null,
                'recurrence_ends_on' => null,
            ]);

            $schedule->loadMissing('items');
            foreach ($schedule->items as $row) {
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
                    'vat_rate' => $row->vat_rate,
                ]);
            }

            $this->references->assignNextNumber($invoice, $company);

            return $invoice->fresh(['client', 'items', 'company']);
        });

        if ($child !== null) {
            $this->trySend($child, $company);
        }

        return $child;
    }

    private function trySend(Invoice $invoice, Company $company): void
    {
        $invoice->loadMissing('client');
        try {
            $this->mailer->send($invoice, $company);
        } catch (\Throwable $e) {
            Log::warning('recurring_invoice_send_failed', [
                'invoice_id' => $invoice->id,
                'company_id' => $company->id,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
