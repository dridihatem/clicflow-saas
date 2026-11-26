<?php

namespace App\Models;

use App\Enums\ProposalStatus;
use App\Models\Concerns\TenantScope;
use Database\Factories\ProposalFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Proposal extends Model
{
    use \App\Models\Concerns\HasDocumentDiscount;
    /** @use HasFactory<ProposalFactory> */
    use HasFactory, HasUlids, SoftDeletes, TenantScope;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => ProposalStatus::class,
            'amount' => 'integer',
            'subtotal_amount' => 'integer',
            'discount_amount' => 'integer',
            'vat_amount' => 'integer',
            'fiscal_stamp_amount' => 'integer',
            'advance_amount_minor' => 'integer',
            'advance_percent' => 'float',
            'payment_terms_json' => 'array',
            'valid_until' => 'date',
            'sent_at' => 'datetime',
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

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function negotiations(): HasMany
    {
        return $this->hasMany(Negotiation::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProposalItem::class)->orderBy('id');
    }

    public function depositInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'deposit_invoice_id');
    }

    public function remainderInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'remainder_invoice_id');
    }

    public function resolvedAdvanceMinor(): int
    {
        if ($this->advance_amount_minor !== null && (int) $this->advance_amount_minor > 0) {
            return (int) $this->advance_amount_minor;
        }

        if ($this->advance_percent !== null && (float) $this->advance_percent > 0) {
            return (int) round(((float) $this->advance_percent / 100) * (int) $this->amount);
        }

        return 0;
    }

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
}
