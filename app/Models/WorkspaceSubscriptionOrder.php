<?php

namespace App\Models;

use App\Enums\WorkspaceSubscriptionOrderStatus;
use App\Models\Concerns\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkspaceSubscriptionOrder extends Model
{
    use HasUlids, TenantScope;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => WorkspaceSubscriptionOrderStatus::class,
            'period_months' => 'integer',
            'amount_minor' => 'integer',
            'metadata' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    public function resolveRouteBinding($value, $field = null)
    {
        $field ??= $this->getRouteKeyName();

        return static::query()
            ->withoutGlobalScopes()
            ->where($field, $value)
            ->firstOrFail();
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isPaid(): bool
    {
        return $this->status === WorkspaceSubscriptionOrderStatus::Paid;
    }

    public function isPending(): bool
    {
        return $this->status === WorkspaceSubscriptionOrderStatus::Pending;
    }
}
