<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Proposal;

class ProposalReferenceService
{
    /**
     * @return array{prefix: string, pad: int, start: int}
     */
    public function referenceSettings(Company $company): array
    {
        $company->loadMissing('settings');
        $templates = is_array($company->settings?->document_templates) ? $company->settings->document_templates : [];
        $prefix = trim((string) ($templates['quote_reference_prefix'] ?? 'DEV'));
        if ($prefix === '') {
            $prefix = 'DEV';
        }
        $pad = max(1, min(12, (int) ($templates['quote_reference_pad'] ?? 3)));
        $start = max(1, (int) ($templates['quote_reference_start'] ?? 1));

        return ['prefix' => $prefix, 'pad' => $pad, 'start' => $start];
    }

    public function examplePreview(Company $company): string
    {
        $year = (int) now()->format('Y');
        $s = $this->referenceSettings($company);

        return $s['prefix'].'-'.str_pad((string) $s['start'], $s['pad'], '0', STR_PAD_LEFT).'-'.$year;
    }

    public function assignNextNumber(Proposal $proposal, Company $company): void
    {
        if ($proposal->number !== null && $proposal->number !== '') {
            return;
        }

        $year = (int) now()->format('Y');
        $s = $this->referenceSettings($company);
        $prefix = $s['prefix'];
        $pad = $s['pad'];
        $start = $s['start'];
        $pattern = '/^'.preg_quote($prefix, '/').'-(\d+)-'.$year.'$/';

        $max = Proposal::query()->withoutGlobalScope('tenant')
            ->where('company_id', $company->id)
            ->whereNotNull('number')
            ->where('id', '!=', $proposal->id)
            ->pluck('number')
            ->reduce(function (int $carry, ?string $n) use ($pattern): int {
                if (! is_string($n) || $n === '') {
                    return $carry;
                }
                if (preg_match($pattern, $n, $m)) {
                    return max($carry, (int) $m[1]);
                }

                return $carry;
            }, 0);

        $next = max($max + 1, $start);

        $proposal->update([
            'number' => $prefix.'-'.str_pad((string) $next, $pad, '0', STR_PAD_LEFT).'-'.$year,
        ]);
    }
}
