<?php

namespace App\Models;

use App\Enums\ProjectSource;
use App\Enums\ProjectStatus;
use App\Models\Concerns\TenantScope;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory, HasUlids, SoftDeletes, TenantScope;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class,
            'source' => ProjectSource::class,
            'final_price' => 'integer',
            'negotiated_price' => 'integer',
            'final_deadline' => 'date',
            'client_price_confirmed_at' => 'datetime',
        ];
    }

    /**
     * Client-facing agreed total in minor units (negotiated price preferred, else final).
     */
    public function clientAgreedPriceMinor(): ?int
    {
        if ($this->negotiated_price !== null) {
            return (int) $this->negotiated_price;
        }
        if ($this->final_price !== null) {
            return (int) $this->final_price;
        }

        return null;
    }

    public function isClientPriceConfirmed(): bool
    {
        return $this->client_price_confirmed_at !== null;
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function proposals(): HasMany
    {
        return $this->hasMany(Proposal::class);
    }

    public function formSubmission(): BelongsTo
    {
        return $this->belongsTo(FormSubmission::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ProjectTask::class)->orderBy('sort_order');
    }

    public function teamMembers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_user')->withTimestamps();
    }

    public function isAccessibleBy(?User $user): bool
    {
        if (! $user instanceof User || $user->company_id === null) {
            return false;
        }

        if ((string) $this->company_id !== (string) $user->company_id) {
            return false;
        }

        if ($user->hasRole('company_admin')) {
            return true;
        }

        if ((string) $this->created_by === (string) $user->id) {
            return true;
        }

        return $this->teamMembers()->where('users.id', $user->id)->exists();
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Project>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Project>
     */
    public function scopeAccessibleBy($query, User $user)
    {
        if ($user->hasRole('company_admin')) {
            return $query;
        }

        return $query->where(function ($q) use ($user): void {
            $q->where('created_by', $user->id)
                ->orWhereHas('teamMembers', fn ($tm) => $tm->where('users.id', $user->id));
        });
    }

    public function files(): HasMany
    {
        return $this->hasMany(ProjectFile::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function installments(): HasMany
    {
        return $this->hasMany(ProjectInstallment::class)->orderBy('sort_order')->orderBy('due_date');
    }
}
