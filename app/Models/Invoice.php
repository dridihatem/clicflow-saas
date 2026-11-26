<?php

namespace App\Models;

use App\Enums\InvoiceKind;
use App\Enums\InvoiceRecurrenceInterval;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\Concerns\TenantScope;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use \App\Models\Concerns\HasDocumentDiscount;
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory, HasUlids, SoftDeletes, TenantScope;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'invoice_kind' => InvoiceKind::class,
            'amount' => 'integer',
            'subtotal_amount' => 'integer',
            'discount_amount' => 'integer',
            'vat_amount' => 'integer',
            'fiscal_stamp_amount' => 'integer',
            'due_date' => 'date',
            'reminder_sent_at' => 'datetime',
            'recurrence_interval' => InvoiceRecurrenceInterval::class,
            'recurrence_every_months' => 'integer',
            'recurrence_next_on' => 'date',
            'recurrence_ends_on' => 'date',
            'recurrence_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(Proposal::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function recurrenceParent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'recurrence_parent_id');
    }

    public function recurrenceChildren(): HasMany
    {
        return $this->hasMany(self::class, 'recurrence_parent_id')->latest();
    }

    public function isRecurringSchedule(): bool
    {
        return (bool) $this->recurrence_active && $this->recurrence_interval !== null;
    }

    public function recurrenceLabel(): ?string
    {
        if (! $this->recurrence_interval instanceof InvoiceRecurrenceInterval) {
            return null;
        }

        return $this->recurrence_interval->label($this->recurrence_every_months);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)
            ->orderByRaw('COALESCE(paid_at, created_at)')
            ->orderBy('created_at')
            ->orderBy('id');
    }

    public function completedPaymentsTotalMinor(): int
    {
        return (int) $this->payments()
            ->where('status', PaymentStatus::Completed)
            ->sum('amount');
    }

    /**
     * Display-only share of document VAT for a line total in minor units (ex. VAT).
     * Document VAT is computed on the invoice subtotal; this splits it proportionally by line.
     */
    public function allocatedVatMinorForLineHt(int $lineTotalHtMinor): int
    {
        $linesHt = $this->linesSubtotalMinor();
        if ($lineTotalHtMinor <= 0 || $linesHt <= 0 || $this->vat_amount <= 0) {
            return 0;
        }

        // VAT is computed after the document discount; spread it over the lines (identical when no discount).
        return (int) round(($lineTotalHtMinor * $this->vat_amount) / $linesHt);
    }

    public function lineTotalTtcDisplayMinor(int $lineTotalHtMinor): int
    {
        return $lineTotalHtMinor + $this->allocatedVatMinorForLineHt($lineTotalHtMinor);
    }

    public function syncStatusWithPayments(): void
    {
        if ($this->status === InvoiceStatus::Cancelled) {
            return;
        }

        $completed = $this->completedPaymentsTotalMinor();

        if ($this->amount > 0 && $completed >= $this->amount) {
            if ($this->status !== InvoiceStatus::Paid) {
                $this->update(['status' => InvoiceStatus::Paid]);
                try {
                    app(\App\Services\ProposalAcceptanceService::class)->handleDepositPaid($this->fresh());
                } catch (\Throwable) {
                    // Keep payment settlement resilient.
                }
            }

            return;
        }

        if ($this->status === InvoiceStatus::Paid) {
            $this->update(['status' => InvoiceStatus::Sent]);
        }
    }

    /** Completed payment total using withSum alias when present, else relation sum. */
    public function completedSumMinor(): int
    {
        if (array_key_exists('payments_sum_amount', $this->attributes) && $this->payments_sum_amount !== null) {
            return (int) $this->payments_sum_amount;
        }

        return $this->completedPaymentsTotalMinor();
    }

    public function paymentSettlementKey(): string
    {
        if ($this->status === InvoiceStatus::Cancelled) {
            return 'cancelled';
        }
        if ($this->amount <= 0) {
            return 'none';
        }
        $paid = $this->completedSumMinor();
        if ($paid >= $this->amount) {
            return 'paid';
        }
        if ($paid > 0) {
            return 'partial';
        }
        if ($this->due_date && $this->due_date->isPast()) {
            return 'overdue';
        }

        return 'unpaid';
    }

    public function paymentSettlementLabel(): string
    {
        return match ($this->paymentSettlementKey()) {
            'paid' => __('settlement.paid'),
            'partial' => __('settlement.partial'),
            'unpaid' => __('settlement.unpaid'),
            'overdue' => __('settlement.overdue'),
            'cancelled' => __('settlement.cancelled'),
            default => '—',
        };
    }

    public function scopeSettlement(Builder $query, string $settlement): Builder
    {
        $done = PaymentStatus::Completed->value;

        return match ($settlement) {
            'paid' => $query->where(function (Builder $q) use ($done): void {
                $q->where('status', InvoiceStatus::Paid)
                    ->orWhereRaw(
                        '(SELECT COALESCE(SUM(amount),0) FROM payments WHERE payments.invoice_id = invoices.id AND payments.status = ?) >= invoices.amount',
                        [$done]
                    );
            })->where('amount', '>', 0),
            'partial' => $query->where('status', '!=', InvoiceStatus::Cancelled)
                ->where('amount', '>', 0)
                ->whereRaw(
                    '(SELECT COALESCE(SUM(amount),0) FROM payments WHERE payments.invoice_id = invoices.id AND payments.status = ?) > 0',
                    [$done]
                )
                ->whereRaw(
                    '(SELECT COALESCE(SUM(amount),0) FROM payments WHERE payments.invoice_id = invoices.id AND payments.status = ?) < invoices.amount',
                    [$done]
                ),
            'unpaid' => $query->where('status', '!=', InvoiceStatus::Cancelled)
                ->where('amount', '>', 0)
                ->where('status', '!=', InvoiceStatus::Paid)
                ->whereRaw(
                    '(SELECT COALESCE(SUM(amount),0) FROM payments WHERE payments.invoice_id = invoices.id AND payments.status = ?) = 0',
                    [$done]
                ),
            'overdue' => $query->where('status', '!=', InvoiceStatus::Cancelled)
                ->where('amount', '>', 0)
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<', now()->toDateString())
                ->whereRaw(
                    '(SELECT COALESCE(SUM(amount),0) FROM payments WHERE payments.invoice_id = invoices.id AND payments.status = ?) < invoices.amount',
                    [$done]
                ),
            default => $query,
        };
    }
}
