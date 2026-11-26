<?php

namespace App\Models;

use App\Enums\ClientCampaignChannel;
use App\Enums\ClientCampaignStatus;
use App\Models\Concerns\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientCampaign extends Model
{
    use HasUlids, SoftDeletes, TenantScope;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'channel' => ClientCampaignChannel::class,
            'status' => ClientCampaignStatus::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
            'budget' => 'decimal:2',
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

    public function prospects(): HasMany
    {
        return $this->hasMany(ClientProspect::class, 'campaign_id');
    }
}
