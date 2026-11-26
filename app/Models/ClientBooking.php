<?php

namespace App\Models;

use App\Enums\ClientBookingStatus;
use App\Models\Concerns\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientBooking extends Model
{
    use HasUlids, SoftDeletes, TenantScope;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => ClientBookingStatus::class,
            'starts_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ClientBookingComment::class)->orderByDesc('created_at');
    }

    public function telHref(): ?string
    {
        $phone = preg_replace('/\s+/', '', (string) $this->phone);

        return $phone !== '' ? 'tel:'.$phone : null;
    }
}
