<?php

namespace App\Models;

use App\Models\Concerns\TenantScope;
use App\Observers\SubscriptionObserver;
use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy([SubscriptionObserver::class])]
class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use HasFactory, TenantScope;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
            'current_period_end' => 'datetime',
            'period_months' => 'integer',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Trial expired, paid period past end, or canceled.
     */
    public function isEnded(): bool
    {
        $status = strtolower((string) $this->status);

        if (in_array($status, ['canceled', 'cancelled', 'expired'], true)) {
            return true;
        }

        if ($status === 'trialing' && $this->trial_ends_at !== null && $this->trial_ends_at->isPast()) {
            return true;
        }

        if ($status === 'active' && $this->current_period_end !== null && $this->current_period_end->isPast()) {
            return true;
        }

        return false;
    }

    /**
     * Human-readable lifecycle for admin lists.
     */
    public function lifecycleLabel(): string
    {
        if ($this->isEnded()) {
            $status = strtolower((string) $this->status);
            if (in_array($status, ['canceled', 'cancelled'], true)) {
                return __('Canceled');
            }
            if ($status === 'trialing') {
                return __('Trial ended');
            }

            return __('Period ended');
        }

        $status = strtolower((string) $this->status);
        if ($status === 'trialing') {
            return __('Trialing');
        }
        if ($status === 'active') {
            return __('Active');
        }

        return ucfirst((string) $this->status);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
