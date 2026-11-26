<?php

namespace App\Observers;

use App\Jobs\SyncEntityToOdooJob;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\OdooSyncMap;
use App\Models\Project;
use App\Models\Proposal;
use App\Services\Odoo\OdooSyncService;

class OdooSyncObserver
{
    public function created(Client|Project|Invoice|Proposal $model): void
    {
        $this->dispatch($model);
    }

    public function updated(Client|Project|Invoice|Proposal $model): void
    {
        $this->dispatch($model);
    }

    private function dispatch(Client|Project|Invoice|Proposal $model): void
    {
        if (OdooSyncService::$suppressObservers) {
            return;
        }

        $companyId = (string) ($model->company_id ?? '');
        $localId = (string) ($model->id ?? '');
        if ($companyId === '' || $localId === '') {
            return;
        }

        $entityType = match (true) {
            $model instanceof Client => OdooSyncMap::ENTITY_CLIENT,
            $model instanceof Project => OdooSyncMap::ENTITY_PROJECT,
            $model instanceof Invoice => OdooSyncMap::ENTITY_INVOICE,
            $model instanceof Proposal => OdooSyncMap::ENTITY_PROPOSAL,
        };

        SyncEntityToOdooJob::dispatch($companyId, $entityType, $localId);
    }
}
