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

class SyncEntityToOdooJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public string $companyId,
        public string $entityType,
        public string $localId,
    ) {}

    public function handle(OdooConfigService $config, OdooSyncService $sync): void
    {
        if (OdooSyncService::$suppressObservers) {
            return;
        }

        $company = Company::query()->withoutGlobalScopes()->find($this->companyId);
        if (! $company || ! $config->isConfigured($company)) {
            return;
        }

        $resolved = $config->getResolved($company);
        if (! $resolved['auto_push']) {
            return;
        }

        $sync->pushEntity($company, $this->entityType, $this->localId);
    }
}
