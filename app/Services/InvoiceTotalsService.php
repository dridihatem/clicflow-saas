<?php

namespace App\Services;

use App\Models\Company;

class InvoiceTotalsService
{
    /**
     * @return array{subtotal: int, vat: int, stamp: int, total: int}
     */
    public function fromSubtotalMinor(int $subtotalMinor, Company $company): array
    {
        $company->loadMissing('settings');
        $billing = $company->settings?->billing;
        $billing = is_array($billing) ? $billing : [];

        $vatPct = isset($billing['vat_percent']) ? (float) $billing['vat_percent'] : 0.0;
        $stampEnabled = filter_var($billing['fiscal_stamp_enabled'] ?? false, FILTER_VALIDATE_BOOL);
        $stampMinor = $stampEnabled && isset($billing['fiscal_stamp_minor'])
            ? max(0, (int) $billing['fiscal_stamp_minor'])
            : 0;

        $vat = $vatPct > 0 ? (int) round($subtotalMinor * $vatPct / 100.0) : 0;

        return [
            'subtotal' => $subtotalMinor,
            'vat' => $vat,
            'stamp' => $stampMinor,
            'total' => $subtotalMinor + $vat + $stampMinor,
        ];
    }

    /** Workspace default VAT % (billing settings). */
    public function companyVatPercent(Company $company): float
    {
        $company->loadMissing('settings');
        $billing = is_array($company->settings?->billing) ? $company->settings->billing : [];

        return isset($billing['vat_percent']) ? (float) $billing['vat_percent'] : 0.0;
    }

    /** Normalized line VAT rate (0 … 100, 3 decimals) or $default when empty / invalid. */
    public static function normalizeRate(mixed $value, ?float $default = null): ?float
    {
        $num = self::parseNumber($value);
        if ($num === null || $num < 0) {
            return $default;
        }

        return round(min(100.0, $num), 3);
    }

    /**
     * Per-line VAT totals. Each row: ['total' => int minor HT, 'vat_rate' => ?float]; a null rate uses the
     * workspace default (so documents without per-line rates compute exactly as before).
     * The document discount is spread over the rate groups proportionally (largest remainder) before VAT.
     *
     * @param  iterable<array{total: int, vat_rate?: mixed}>  $rows
     * @return array{lines: int, discount: int, discount_type: ?string, discount_value: ?float, subtotal: int, vat: int, stamp: int, total: int, breakdown: list<array{rate: float, base: int, vat: int}>}
     */
    public function fromItemsMinor(iterable $rows, Company $company, ?string $discountType = null, mixed $discountValue = null, string $currency = 'EUR'): array
    {
        $default = $this->companyVatPercent($company);
        $groups = [];
        $lines = 0;
        foreach ($rows as $row) {
            $total = (int) ($row['total'] ?? 0);
            $rate = self::normalizeRate($row['vat_rate'] ?? null, $default) ?? 0.0;
            $key = number_format($rate, 3, '.', '');
            $groups[$key] = ($groups[$key] ?? 0) + $total;
            $lines += $total;
        }
        if ($groups === []) {
            $groups[number_format($default, 3, '.', '')] = 0;
        }

        [$type, $value, $discount] = $this->discount($lines, $discountType, $discountValue, $currency);
        $base = $this->fromSubtotalMinor($lines - $discount, $company);

        $breakdown = self::splitVat($groups, $lines, $discount);
        $vat = array_sum(array_column($breakdown, 'vat'));

        return [
            'lines' => $lines,
            'discount' => $discount,
            'discount_type' => $type,
            'discount_value' => $value,
            'subtotal' => $lines - $discount,
            'vat' => $vat,
            'stamp' => $base['stamp'],
            'total' => $lines - $discount + $vat + $base['stamp'],
            'breakdown' => $breakdown,
        ];
    }

    /**
     * @param  array<string, int>  $groups  rate key => lines HT
     * @return list<array{rate: float, base: int, vat: int}>
     */
    public static function splitVat(array $groups, int $lines, int $discount): array
    {
        $alloc = [];
        $given = 0;
        $largest = null;
        foreach ($groups as $key => $ht) {
            $d = $lines > 0 ? intdiv($discount * $ht, $lines) : 0;
            $alloc[$key] = $d;
            $given += $d;
            if ($largest === null || $ht > $groups[$largest]) {
                $largest = $key;
            }
        }
        if ($largest !== null) {
            $alloc[$largest] += $discount - $given;
        }
        $out = [];
        foreach ($groups as $key => $ht) {
            $rate = (float) $key;
            $net = $ht - $alloc[$key];
            $out[] = ['rate' => $rate, 'base' => $net, 'vat' => $rate > 0 ? (int) round($net * $rate / 100.0) : 0];
        }
        usort($out, fn ($a, $b) => $b['rate'] <=> $a['rate']);

        return $out;
    }

    /** "20 %", "5,5 %" */
    public static function rateLabel(float $rate): string
    {
        return rtrim(rtrim(number_format($rate, 3, ',', ''), '0'), ',').' %';
    }

    /**
     * Rows for fromItemsMinor() from validated form items (quantity × unit_amount, vat_rate).
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return list<array{total: int, vat_rate: ?float}>
     */
    public static function rowsFromFormItems(array $items): array
    {
        return array_values(array_map(fn ($row) => [
            'total' => (int) ($row['quantity'] ?? 0) * (int) ($row['unit_amount'] ?? 0),
            'vat_rate' => self::normalizeRate($row['vat_rate'] ?? null),
        ], $items));
    }

    /**
     * Totals from the sum of line totals with an optional document discount applied before VAT.
     *
     * @return array{lines: int, discount: int, discount_type: ?string, discount_value: ?float, subtotal: int, vat: int, stamp: int, total: int}
     */
    public function fromLinesMinor(int $linesMinor, Company $company, ?string $discountType = null, mixed $discountValue = null, string $currency = 'EUR'): array
    {
        [$type, $value, $discount] = $this->discount($linesMinor, $discountType, $discountValue, $currency);

        return ['lines' => $linesMinor, 'discount' => $discount, 'discount_type' => $type, 'discount_value' => $value]
            + $this->fromSubtotalMinor($linesMinor - $discount, $company);
    }

    /**
     * @return array{0: ?string, 1: ?float, 2: int} normalized type, value and discount in minor units (0 … lines)
     */
    public function discount(int $linesMinor, ?string $type, mixed $value, string $currency): array
    {
        $type = in_array($type, ['percent', 'amount'], true) ? $type : null;
        $num = self::parseNumber($value);
        if ($type === null || $num === null || $num <= 0 || $linesMinor <= 0) {
            return [null, null, 0];
        }
        if ($type === 'percent') {
            $num = min(100.0, $num);
            $minor = (int) round($linesMinor * $num / 100.0);
        } else {
            $minor = flowdesk_decimal_to_minor((string) $num, $currency);
        }

        return [$type, $num, max(0, min($linesMinor, (int) $minor))];
    }

    public static function parseNumber(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        $s = str_replace([' ', "\u{00A0}", '%', '€'], '', trim((string) $value));
        if (str_contains($s, ',') && str_contains($s, '.')) {
            $s = strrpos($s, ',') > strrpos($s, '.') ? str_replace(['.', ','], ['', '.'], $s) : str_replace(',', '', $s);
        } else {
            $s = str_replace(',', '.', $s);
        }

        return is_numeric($s) ? (float) $s : null;
    }

    /** Validation rules shared by invoice and quote forms. @return array<string, mixed> */
    public static function discountRules(): array
    {
        return [
            'discount_type' => ['nullable', 'string', 'in:percent,amount'],
            'discount_value' => ['nullable', 'string', 'max:24', 'regex:/^\s*\d[\d\s.,]*\s*%?\s*$/'],
        ];
    }

    /** Validation rule for a line VAT rate (items.*.vat_rate). @return list<string> */
    public static function lineRateRule(): array
    {
        return ['nullable', 'string', 'max:10', 'regex:/^\s*\d{1,3}([.,]\d{1,3})?\s*%?\s*$/'];
    }
}
