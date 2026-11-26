<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class McpToken extends Model
{
    use HasUlids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'scopes' => 'array',
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
            'expires_at' => 'datetime',
            'refresh_rotated_at' => 'datetime',
            'refresh_expires_at' => 'datetime',
        ];
    }

    public const AUDIENCE_COMPANY = 'company';

    public const AUDIENCE_ADMIN = 'admin';

    /**
     * @return array{token: self, plain: string}
     */
    public static function issue(string $audience, string $tokenableId, string $name, array $scopes): array
    {
        $plain = 'cfm_'.Str::lower(Str::random(48));
        $token = self::query()->create([
            'tokenable_type' => $audience,
            'tokenable_id' => $tokenableId,
            'name' => $name,
            'token_prefix' => substr($plain, 0, 10),
            'token_hash' => hash('sha256', $plain),
            'scopes' => array_values(array_unique(array_intersect($scopes, ['read', 'write']))),
        ]);

        return ['token' => $token, 'plain' => $plain];
    }

    public static function findActiveByPlain(string $plain): ?self
    {
        $plain = trim($plain);
        if ($plain === '') {
            return null;
        }

        $token = self::query()
            ->where('token_hash', hash('sha256', $plain))
            ->whereNull('revoked_at')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->first();

        return $token instanceof self ? $token : null;
    }

    /** Connection created through the OAuth flow (ChatGPT, Claude…) rather than a manual key. */
    public function isOAuth(): bool
    {
        return $this->oauth_client_id !== null && $this->oauth_client_id !== '';
    }

    /** OAuth scope names (mcp:read / mcp:write) for this token. */
    public function oauthScopeString(): string
    {
        return implode(' ', array_map(fn ($s) => 'mcp:'.$s, $this->scopes ?? []));
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function canRead(): bool
    {
        $scopes = $this->scopes ?? [];

        return in_array('read', $scopes, true) || in_array('write', $scopes, true);
    }

    public function canWrite(): bool
    {
        return in_array('write', $this->scopes ?? [], true);
    }

    public function revoke(): void
    {
        if ($this->revoked_at === null) {
            $this->forceFill(['revoked_at' => now()])->save();
        }
    }

    public function touchLastUsed(): void
    {
        $this->forceFill(['last_used_at' => now()])->saveQuietly();
    }

    public function company(): ?Company
    {
        if ($this->tokenable_type !== self::AUDIENCE_COMPANY) {
            return null;
        }

        return Company::query()->withoutGlobalScopes()->find($this->tokenable_id);
    }

    public function admin(): ?Admin
    {
        if ($this->tokenable_type !== self::AUDIENCE_ADMIN) {
            return null;
        }

        return Admin::query()->find($this->tokenable_id);
    }
}
