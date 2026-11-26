<?php

namespace App\Models;

use App\Enums\ProductKind;
use App\Models\Concerns\TenantScope;
use App\Support\CatalogUnits;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasUlids, TenantScope;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'base_price_minor' => 'integer',
            'tax_rate' => 'decimal:2',
            'tax_rate_2' => 'decimal:2',
            'track_stock' => 'boolean',
            'is_active' => 'boolean',
            'kind' => ProductKind::class,
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function isService(): bool
    {
        return $this->kind === ProductKind::Service;
    }

    public function unitLabel(): string
    {
        return CatalogUnits::label($this->unit);
    }

    public function defaultVariant(): ?ProductVariant
    {
        return $this->variants->first();
    }

    /** Human label such as "20 %" or "20 % + 1 %"; empty when no tax is set. */
    public function taxLabel(): string
    {
        $fmt = static fn ($v): string => flowdesk_format_percent($v);
        $parts = [];
        if ($this->tax_rate !== null) {
            $parts[] = $fmt($this->tax_rate);
        }
        if ($this->tax_rate_2 !== null && (float) $this->tax_rate_2 > 0) {
            $parts[] = $fmt($this->tax_rate_2);
        }

        return implode(' + ', $parts);
    }
}
