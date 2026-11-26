<?php

namespace App\Models;

use App\Enums\ClientContractStatus;
use App\Models\Concerns\TenantScope;
use Database\Factories\ClientContractFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientContract extends Model
{
    /** @use HasFactory<ClientContractFactory> */
    use HasFactory, HasUlids, SoftDeletes, TenantScope;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => ClientContractStatus::class,
            'meta' => 'array',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'sent_at' => 'datetime',
            'company_signed_at' => 'datetime',
            'client_signed_at' => 'datetime',
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

    public function template(): BelongsTo
    {
        return $this->belongsTo(ContractTemplate::class, 'contract_template_id');
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [ClientContractStatus::Draft, ClientContractStatus::Sent], true);
    }

    public function canSend(): bool
    {
        return $this->status === ClientContractStatus::Draft;
    }

    public function canResend(): bool
    {
        return $this->status === ClientContractStatus::Sent;
    }

    public function isVisibleInPortal(): bool
    {
        return in_array($this->status, [ClientContractStatus::Sent, ClientContractStatus::Signed], true);
    }
}
