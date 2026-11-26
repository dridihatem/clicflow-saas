<?php

namespace App\Models;

use App\Enums\RealEstatePropertyType;
use App\Enums\RealEstateTransactionType;
use App\Models\Concerns\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RealEstateOffer extends Model
{
    use HasUlids, TenantScope;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'property_type' => RealEstatePropertyType::class,
            'transaction_type' => RealEstateTransactionType::class,
            'price_minor' => 'integer',
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

    public function images(): HasMany
    {
        return $this->hasMany(RealEstateOfferImage::class)->orderBy('sort_order');
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(RealEstateContract::class);
    }
}
