<?php

namespace App\Services;

use App\Models\ClientContract;
use App\Models\Company;
use Illuminate\Support\Facades\DB;

class ContractReferenceService
{
    public function next(Company $company): string
    {
        $year = now()->format('Y');
        $prefix = 'CTR-'.$year.'-';

        return DB::transaction(function () use ($company, $prefix): string {
            $latest = ClientContract::query()
                ->withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->where('reference', 'like', $prefix.'%')
                ->lockForUpdate()
                ->orderByDesc('reference')
                ->value('reference');

            $seq = 1;
            if (is_string($latest) && preg_match('/CTR-\d{4}-(\d+)$/', $latest, $m)) {
                $seq = (int) $m[1] + 1;
            }

            return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
        });
    }
}
