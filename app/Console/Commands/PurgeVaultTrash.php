<?php

namespace App\Console\Commands;

use App\Services\Vault\VaultTrashService;
use Illuminate\Console\Command;

class PurgeVaultTrash extends Command
{
    protected $signature = 'vault:purge-trash {--days= : Retention in days (default 30)}';

    protected $description = 'Supprime définitivement les fichiers du Coffre-fort restés plus de 30 jours dans la corbeille.';

    public function handle(VaultTrashService $trash): int
    {
        $days = $this->option('days') !== null ? max(1, (int) $this->option('days')) : $trash->retentionDays();
        $count = $trash->purgeExpired($days);
        $this->info("Vault trash purge: {$count} file(s) older than {$days} day(s) removed.");

        return self::SUCCESS;
    }
}
