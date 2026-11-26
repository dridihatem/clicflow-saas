<?php

namespace App\Models;

use App\Models\Concerns\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class VaultFileShare extends Model
{
    use HasUlids, TenantScope;

    protected $fillable = [
        'company_id', 'vault_file_id', 'token', 'password_hash', 'expires_at', 'max_downloads',
        'download_count', 'view_count', 'allow_preview', 'label', 'created_by', 'revoked_at', 'last_accessed_at',
    ];

    protected $hidden = ['password_hash'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'last_accessed_at' => 'datetime',
            'allow_preview' => 'boolean',
            'max_downloads' => 'integer',
            'download_count' => 'integer',
            'view_count' => 'integer',
        ];
    }

    public static function newToken(): string
    {
        return Str::random(64);
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(VaultFile::class, 'vault_file_id')->withoutGlobalScopes();
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function hasPassword(): bool
    {
        return filled($this->password_hash);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isExhausted(): bool
    {
        return $this->max_downloads !== null && $this->download_count >= $this->max_downloads;
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isActive(): bool
    {
        return ! $this->isRevoked() && ! $this->isExpired() && ! $this->isExhausted();
    }

    /** active | expired | exhausted | revoked */
    public function state(): string
    {
        return match (true) {
            $this->isRevoked() => 'revoked',
            $this->isExpired() => 'expired',
            $this->isExhausted() => 'exhausted',
            default => 'active',
        };
    }

    public function publicUrl(): string
    {
        $company = $this->relationLoaded('company') ? $this->company : Company::query()->find($this->company_id);

        return flowdesk_tenant_url($company, route('vault-share.show', $this->token, false));
    }
}
