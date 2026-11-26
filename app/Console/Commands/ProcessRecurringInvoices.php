<?php

namespace App\Console\Commands;

use App\Services\RecurringInvoiceService;
use Illuminate\Console\Command;

class ProcessRecurringInvoices extends Command
{
    protected $signature = 'invoices:process-recurring';

    protected $description = 'Generate due recurring invoices, email clients, and publish them to the portal.';

    public function handle(RecurringInvoiceService $recurring): int
    {
        $count = $recurring->processDue();
        $this->info("Generated {$count} recurring invoice(s).");

        return self::SUCCESS;
    }
}
