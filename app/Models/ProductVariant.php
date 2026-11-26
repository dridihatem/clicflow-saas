<?php

namespace App\Models;

use App\Models\Concerns\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariant extends Model
{
    use HasUlids, TenantScope;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'price_minor' => 'integer',
            'stock_qty' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function unitPriceMinor(): int
    {
        if ($this->price_minor !== null) {
            return (int) $this->price_minor;
        }

        return (int) ($this->product?->base_price_minor ?? 0);
    }

    public function label(): string
    {
        $parts = array_filter([$this->color, $this->size, $this->sku]);
        $suffix = $parts ? ' ('.implode(' / ', $parts).')' : '';

        return ($this->product?->name ?? 'Variant').$suffix;
    }
}
