<?php

namespace App\Models;

use App\Models\Concerns\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OdooSyncMap extends Model
{
    use HasUlids, TenantScope;

    public const ENTITY_CLIENT = 'client';

    public const ENTITY_PROJECT = 'project';

    public const ENTITY_INVOICE = 'invoice';

    public const ENTITY_PROPOSAL = 'proposal';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'odoo_id' => 'integer',
            'last_synced_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
