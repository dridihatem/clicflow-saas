<?php

namespace App\Models;

use App\Enums\ClientProspectStatus;
use App\Models\Concerns\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientProspect extends Model
{
    use HasUlids, SoftDeletes, TenantScope;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => ClientProspectStatus::class,
            'captured_on' => 'date',
            'appointment_at' => 'datetime',
            'appointment_status' => \App\Enums\ClientAppointmentStatus::class,
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ClientProspectComment::class)->orderByDesc('created_at');
    }

    public function campaignRelation(): BelongsTo
    {
        return $this->belongsTo(ClientCampaign::class, 'campaign_id');
    }

    public function campaignDisplayName(): string
    {
        $name = $this->campaignRelation?->name ?? $this->campaign ?? '';

        return trim((string) $name);
    }

    public function isClientSourced(): bool
    {
        return ($this->source ?? '') === 'client';
    }

    public function telHref(): ?string
    {
        $phone = preg_replace('/\s+/', '', (string) $this->phone);

        return $phone !== '' ? 'tel:'.$phone : null;
    }
}
