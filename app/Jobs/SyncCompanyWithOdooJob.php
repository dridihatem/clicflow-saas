<?php

namespace App\Jobs;

use App\Models\Company;
use App\Services\Odoo\OdooConfigService;
use App\Services\Odoo\OdooSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SyncCompanyWithOdooJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public string $companyId,
    ) {}

    public function handle(OdooConfigService $config, OdooSyncService $sync): void
    {
        $company = Company::query()->withoutGlobalScopes()->find($this->companyId);
        if (! $company || ! $config->isConfigured($company)) {
            return;
        }

        try {
            $sync->syncCompany($company);
        } catch (Throwable $e) {
            $config->rememberSyncResult($company, 'failed', $e->getMessage());
            throw $e;
        }
    }
}
