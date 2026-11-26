<?php

namespace App\Jobs;

use App\Models\Company;
use App\Models\User;
use App\Services\Integrations\QuickBooksSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncQuickBooksDataJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $companyId, public string $userId) {}

    public function handle(QuickBooksSyncService $quickbooks): void
    {
        $company = Company::query()->find($this->companyId);
        $user = User::query()->find($this->userId);
        if (! $company || ! $user) {
            return;
        }

        $quickbooks->pull($company, $user);
    }
}
