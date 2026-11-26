<?php

namespace App\Models\Concerns;

/**
 * Document discount helpers (invoices, proposals). subtotal_amount is the net HT after discount.
 */
trait HasDocumentDiscount
{
    public function hasDiscount(): bool
    {
        return (int) ($this->discount_amount ?? 0) > 0;
    }

    /** Sum of line totals HT (before the document discount). */
    public function linesSubtotalMinor(): int
    {
        return (int) $this->subtotal_amount + (int) ($this->discount_amount ?? 0);
    }

    /** "10 %" for a percent discount, "" for a fixed amount. */
    public function discountRateLabel(): string
    {
        if (($this->discount_type ?? null) !== 'percent') {
            return '';
        }
        $v = rtrim(rtrim(number_format((float) $this->discount_value, 2, ',', ''), '0'), ',');

        return $v.' %';
    }

    /** True when at least one line carries its own VAT rate (documents saved since per-line VAT). */
    public function hasLineVatRates(): bool
    {
        return $this->items->contains(fn ($i) => $i->vat_rate !== null);
    }

    /** Single rate implied by the stored totals (legacy documents). */
    public function legacyVatPercent(): float
    {
        $sub = (int) $this->subtotal_amount;
        $vat = (int) $this->vat_amount;

        return ($sub > 0 && $vat > 0) ? round(100.0 * $vat / $sub, 2) : 0.0;
    }

    public function lineVatRate($item): float
    {
        return $item->vat_rate !== null ? (float) $item->vat_rate : $this->legacyVatPercent();
    }

    public function lineVatRateLabel($item): string
    {
        return \App\Services\InvoiceTotalsService::rateLabel($this->lineVatRate($item));
    }

    /**
     * VAT per rate for display. Legacy documents: one row with the stored VAT. The rows always add up to
     * the stored vat_amount (any rounding difference goes to the largest row).
     *
     * @return list<array{rate: float, base: int, vat: int, label: string}>
     */
    public function vatBreakdown(): array
    {
        $vatStored = (int) $this->vat_amount;
        if (! $this->hasLineVatRates()) {
            $rate = $this->legacyVatPercent();
            $rows = [['rate' => $rate, 'base' => (int) $this->subtotal_amount, 'vat' => $vatStored]];
        } else {
            $groups = [];
            foreach ($this->items as $item) {
                $key = number_format($this->lineVatRate($item), 3, '.', '');
                $groups[$key] = ($groups[$key] ?? 0) + (int) $item->total_amount;
            }
            $rows = \App\Services\InvoiceTotalsService::splitVat($groups, (int) array_sum($groups), (int) ($this->discount_amount ?? 0));
            $diff = $vatStored - array_sum(array_column($rows, 'vat'));
            if ($diff !== 0 && $rows !== []) {
                $idx = array_keys(array_column($rows, 'vat'), max(array_column($rows, 'vat')))[0];
                $rows[$idx]['vat'] += $diff;
            }
        }

        return array_map(fn ($r) => $r + ['label' => \App\Services\InvoiceTotalsService::rateLabel((float) $r['rate'])], $rows);
    }

    /** Display VAT of one line (after the proportional share of the document discount). */
    public function lineVatMinor($item): int
    {
        $ht = (int) $item->total_amount;
        if (! $this->hasLineVatRates()) {
            return $this->allocatedVatMinorForLineHt($ht);
        }
        $lines = $this->linesSubtotalMinor();
        $net = $lines > 0 ? $ht * ((int) $this->subtotal_amount / $lines) : $ht;

        return (int) round($net * $this->lineVatRate($item) / 100.0);
    }

    public function lineTtcMinor($item): int
    {
        return (int) $item->total_amount + $this->lineVatMinor($item);
    }

    /** @return array{discount_type: ?string, discount_value: ?string, discount_amount: int} */
    public function discountAttributes(): array
    {
        return [
            'discount_type' => $this->discount_type ?? null,
            'discount_value' => $this->discount_value ?? null,
            'discount_amount' => (int) ($this->discount_amount ?? 0),
        ];
    }
}
