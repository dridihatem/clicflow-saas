<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'company_id', 'locale', 'default_currency', 'appearance_json', 'skills_json'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_recovery_codes' => 'array',
            'two_factor_confirmed_at' => 'datetime',
            'appearance_json' => 'array',
            'skills_json' => 'array',
            'last_login_at' => 'datetime',
            'tour_dismissed_at' => 'datetime',
        ];
    }

    /**
     * @return list<string>
     */
    public function skills(): array
    {
        $skills = $this->skills_json;
        if (! is_array($skills)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn ($skill) => is_string($skill) ? trim($skill) : '',
            $skills,
        )));
    }

    public function skillsLabel(): string
    {
        return implode(', ', $this->skills());
    }

    public function assigneeOptionLabel(): string
    {
        $skills = $this->skillsLabel();

        return $skills === '' ? $this->name : $this->name.' · '.$skills;
    }

    public function initials(): string
    {
        $name = trim((string) $this->name);
        if ($name === '') {
            return '?';
        }

        $words = preg_split('/\s+/u', $name) ?: [];
        if (count($words) >= 2) {
            return mb_strtoupper(mb_substr($words[0], 0, 1).mb_substr($words[count($words) - 1], 0, 1));
        }

        return mb_strtoupper(mb_substr($name, 0, 2));
    }

    public function isCompanyAdmin(): bool
    {
        return $this->hasRole('company_admin');
    }

    public function isTeamMemberOnly(): bool
    {
        return $this->hasRole('team_member') && ! $this->hasRole('company_admin');
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_secret !== null
            && $this->two_factor_secret !== ''
            && $this->two_factor_confirmed_at !== null;
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    public function providerProfile(): HasOne
    {
        return $this->hasOne(Provider::class);
    }

    public function clientProfile(): HasOne
    {
        return $this->hasOne(Client::class, 'user_id');
    }

    public function assignedProjects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_user')->withTimestamps();
    }

    /**
     * Workspace operators (excludes client portal accounts).
     */
    public function scopeWorkspaceStaff(Builder $query): Builder
    {
        return $query->whereDoesntHave('roles', function ($q): void {
            $q->where('name', 'client');
        });
    }
}
