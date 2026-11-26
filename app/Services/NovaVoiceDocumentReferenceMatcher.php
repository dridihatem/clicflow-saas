<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Proposal;
use App\Models\User;

/**
 * Match spoken document references with or without separators
 * (INV0012026 / INV 001 2026 → INV-001-2026).
 */
class NovaVoiceDocumentReferenceMatcher
{
    /**
     * Strip separators so spoken refs match stored numbers.
     */
    public function compact(string $value): string
    {
        return mb_strtolower(preg_replace('/[^a-z0-9]/iu', '', $value) ?? '');
    }

    /**
     * SQL expression that removes common reference separators (- _ space . /).
     */
    public function sqlCompact(string $column = 'number'): string
    {
        $col = preg_replace('/[^a-zA-Z0-9_.]/', '', $column) ?: 'number';

        return "LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE({$col}, ''), '-', ''), '_', ''), ' ', ''), '.', ''), '/', ''))";
    }

    public function findInvoice(User $user, string $target): ?Invoice
    {
        $target = trim($target);
        if ($target === '') {
            return null;
        }

        $compact = $this->compact($target);
        $lower = mb_strtolower($target);
        $compactSql = $this->sqlCompact('number');

        $query = Invoice::query()->where(function ($q) use ($target, $lower, $compact, $compactSql): void {
            $q->whereRaw('LOWER(number) = ?', [$lower])
                ->orWhere('number', 'like', '%'.$target.'%');

            if ($compact !== '') {
                $q->orWhereRaw($compactSql.' = ?', [$compact]);
                if (strlen($compact) >= 6) {
                    $q->orWhereRaw($compactSql.' LIKE ?', ['%'.$compact.'%']);
                }
            }
        });

        if ($user->company_id) {
            $query->where('company_id', $user->company_id);
        }

        return $query
            ->orderByRaw(
                'CASE WHEN LOWER(number) = ? THEN 0 WHEN '.$compactSql.' = ? THEN 1 WHEN LOWER(number) LIKE ? THEN 2 ELSE 3 END',
                [$lower, $compact !== '' ? $compact : $lower, $lower.'%'],
            )
            ->first();
    }

    public function findProposal(User $user, string $target): ?Proposal
    {
        $target = trim($target);
        if ($target === '') {
            return null;
        }

        $compact = $this->compact($target);
        $lower = mb_strtolower($target);
        $compactSql = $this->sqlCompact('number');

        $query = Proposal::query()->where(function ($q) use ($target, $lower, $compact, $compactSql): void {
            $q->whereRaw('LOWER(number) = ?', [$lower])
                ->orWhere('number', 'like', '%'.$target.'%')
                ->orWhere('name', 'like', '%'.$target.'%');

            if ($compact !== '') {
                $q->orWhereRaw($compactSql.' = ?', [$compact]);
                if (strlen($compact) >= 6) {
                    $q->orWhereRaw($compactSql.' LIKE ?', ['%'.$compact.'%']);
                }
            }
        });

        if ($user->company_id) {
            $query->where('company_id', $user->company_id);
        }

        return $query
            ->orderByRaw(
                'CASE WHEN LOWER(number) = ? THEN 0 WHEN '.$compactSql.' = ? THEN 1 WHEN LOWER(number) LIKE ? THEN 2 ELSE 3 END',
                [$lower, $compact !== '' ? $compact : $lower, $lower.'%'],
            )
            ->first();
    }
}
