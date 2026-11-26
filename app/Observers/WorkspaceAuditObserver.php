<?php

namespace App\Observers;

use App\Services\AuditLogService;
use Illuminate\Database\Eloquent\Model;

class WorkspaceAuditObserver
{
    public function __construct(private AuditLogService $audit) {}

    public function created(Model $model): void
    {
        $this->audit->record('created', $model);
    }

    public function updated(Model $model): void
    {
        $changed = array_keys($model->getChanges());
        $noise = ['updated_at', 'created_at', 'remember_token', 'password'];
        $meaningful = array_values(array_diff($changed, $noise));

        if ($meaningful === []) {
            return;
        }

        $this->audit->record('updated', $model, [
            'changed' => $meaningful,
        ]);
    }

    public function deleted(Model $model): void
    {
        $this->audit->record(
            method_exists($model, 'isForceDeleting') && $model->isForceDeleting()
                ? 'force_deleted'
                : 'deleted',
            $model,
        );
    }

    public function restored(Model $model): void
    {
        $this->audit->record('restored', $model);
    }
}
