<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class McpOAuthClient extends Model
{
    use HasUlids;

    protected $table = 'mcp_oauth_clients';

    protected $guarded = ['id'];

    protected $hidden = ['client_secret_hash'];

    protected function casts(): array
    {
        return [
            'redirect_uris' => 'array',
            'last_used_at' => 'datetime',
        ];
    }

    public function isConfidential(): bool
    {
        return $this->client_secret_hash !== null && $this->client_secret_hash !== '';
    }

    public function hasRedirectUri(string $uri): bool
    {
        return in_array($uri, $this->redirect_uris ?? [], true);
    }

    public function secretMatches(?string $secret): bool
    {
        if (! $this->isConfidential()) {
            return true;
        }

        return is_string($secret) && $secret !== '' && hash_equals((string) $this->client_secret_hash, hash('sha256', $secret));
    }
}
