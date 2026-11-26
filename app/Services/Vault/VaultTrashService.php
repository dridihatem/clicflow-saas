<?php

namespace App\Services\Vault;

use App\Models\VaultFile;
use Illuminate\Support\Facades\Storage;

/** Corbeille du Coffre-fort : suppression définitive (fichier physique + ligne). */
class VaultTrashService
{
    public function retentionDays(): int
    {
        return max(1, (int) config('flowdesk.vault.trash_days', 30));
    }

    /** Permanently removes a soft-deleted vault file. Only ever called on trashed rows. */
    public function purge(VaultFile $file): void
    {
        if (! $file->trashed()) {
            return;
        }
        if (filled($file->path) && Storage::disk($file->disk ?: 'local')->exists($file->path)) {
            Storage::disk($file->disk ?: 'local')->delete($file->path);
        }
        $file->sharedUsers()->detach();
        $file->shareLinks()->delete();
        $file->forceDelete();
    }

    /** Purges trashed vault files older than the retention window. Returns the count. */
    public function purgeExpired(?int $days = null): int
    {
        $days ??= $this->retentionDays();
        $count = 0;
        VaultFile::query()->withoutGlobalScopes()->onlyTrashed()
            ->where('deleted_at', '<', now()->subDays($days))
            ->orderBy('deleted_at')
            ->chunkById(100, function ($files) use (&$count) {
                foreach ($files as $file) {
                    $this->purge($file);
                    $count++;
                }
            });

        return $count;
    }
}
