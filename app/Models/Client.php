<?php

namespace App\Models;

use App\Enums\ClientStatus;
use App\Models\Concerns\TenantScope;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use HasFactory, HasUlids, SoftDeletes, TenantScope;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'portal_visibility' => 'array',
            'address' => 'array',
            'status' => ClientStatus::class,
            'portal_disabled_at' => 'datetime',
        ];
    }

    /**
     * Postal address as printable lines: street, "postal code + city", country.
     *
     * @return list<string>
     */
    public function addressLines(): array
    {
        $a = is_array($this->address) ? $this->address : [];
        $street = trim((string) ($a['line1'] ?? ($a['street'] ?? '')));
        $cityLine = trim(trim((string) ($a['postal_code'] ?? ($a['zip'] ?? ''))).' '.trim((string) ($a['city'] ?? '')));
        $country = trim((string) ($a['country'] ?? ''));

        return array_values(array_filter([$street, $cityLine, $country], fn ($v) => $v !== ''));
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function proposals(): HasMany
    {
        return $this->hasMany(Proposal::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(ClientContract::class);
    }

    public function payments(): HasManyThrough
    {
        return $this->hasManyThrough(Payment::class, Invoice::class);
    }

    public function feedbacks(): HasMany
    {
        return $this->hasMany(ClientFeedback::class)->latest();
    }

    public function notes(): HasMany
    {
        return $this->hasMany(ClientNote::class)->latest('noted_on')->latest();
    }

    public function calendarEvents(): HasMany
    {
        return $this->hasMany(WorkspaceCalendarEvent::class)->latest('starts_on');
    }

    public function prospects(): HasMany
    {
        return $this->hasMany(ClientProspect::class);
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(ClientCampaign::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(ClientBooking::class);
    }

    public function isPortalEnabled(): bool
    {
        return $this->user_id !== null && $this->portal_disabled_at === null;
    }

    public function isPortalDisabled(): bool
    {
        return $this->user_id !== null && $this->portal_disabled_at !== null;
    }
}
