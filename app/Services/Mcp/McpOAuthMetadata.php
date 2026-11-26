<?php

namespace App\Services\Mcp;

use App\Models\Company;
use Illuminate\Http\Request;

/**
 * OAuth 2.1 / MCP authorization (2025-06-18) metadata for the workspace MCP server.
 * Every host (tenant subdomain or app.clicflow.fr) is its own issuer + protected resource.
 */
class McpOAuthMetadata
{
    public const SCOPES = ['mcp:read', 'mcp:write'];

    public const ACCESS_TTL = 3600;          // 1 hour

    public const REFRESH_TTL_DAYS = 60;      // sliding, rotated on every refresh

    public const CODE_TTL = 600;             // 10 minutes

    /** Display name shown in ChatGPT / Claude and on the consent page: "<Company> - ClicFlow". */
    public static function displayName(?Company $company = null): string
    {
        if ($company === null && app()->bound('currentCompany')) {
            $current = app('currentCompany');
            $company = $current instanceof Company ? $current : null;
        }
        $name = $company ? trim((string) $company->name) : '';

        return $name !== '' ? mb_substr($name, 0, 80).' - ClicFlow' : 'ClicFlow';
    }

    public static function issuer(Request $request): string
    {
        return rtrim($request->getSchemeAndHttpHost(), '/');
    }

    public static function resource(Request $request): string
    {
        return self::issuer($request).'/mcp';
    }

    public static function protectedResourceUrl(Request $request): string
    {
        return self::issuer($request).'/.well-known/oauth-protected-resource/mcp';
    }

    /** @return array<string, mixed> */
    public static function protectedResource(Request $request): array
    {
        return [
            'resource' => self::resource($request),
            'authorization_servers' => [self::issuer($request)],
            'scopes_supported' => self::SCOPES,
            'bearer_methods_supported' => ['header'],
            'resource_name' => self::displayName(),
            'resource_documentation' => self::issuer($request).'/settings/mcp',
        ];
    }

    /** @return array<string, mixed> */
    public static function authorizationServer(Request $request): array
    {
        $iss = self::issuer($request);

        return [
            'issuer' => $iss,
            'authorization_endpoint' => $iss.'/mcp/oauth/authorize',
            'token_endpoint' => $iss.'/mcp/oauth/token',
            'registration_endpoint' => $iss.'/mcp/oauth/register',
            'revocation_endpoint' => $iss.'/mcp/oauth/revoke',
            'scopes_supported' => self::SCOPES,
            'response_types_supported' => ['code'],
            'response_modes_supported' => ['query'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'token_endpoint_auth_methods_supported' => ['none', 'client_secret_post', 'client_secret_basic'],
            'revocation_endpoint_auth_methods_supported' => ['none', 'client_secret_post', 'client_secret_basic'],
            'code_challenge_methods_supported' => ['S256'],
            'authorization_response_iss_parameter_supported' => true,
            'service_documentation' => $iss.'/settings/mcp',
        ];
    }

    /**
     * Strict allow-list of redirect URIs accepted at registration (exact match is then enforced at /authorize).
     */
    public static function redirectUriAllowed(string $uri): bool
    {
        if ($uri === '' || strlen($uri) > 500 || str_contains($uri, '#') || preg_match('/\s/', $uri)) {
            return false;
        }

        $patterns = [
            // ChatGPT connectors / apps
            '#^https://chatgpt\.com/connector_platform_oauth_redirect$#',
            '#^https://chatgpt\.com/connector/oauth/[A-Za-z0-9_\-]{1,128}$#',
            '#^https://(chatgpt\.com|chat\.openai\.com)/aip/[A-Za-z0-9_\-]{1,128}/oauth/callback$#',
            '#^https://platform\.openai\.com/apps-manage/oauth$#',
            // Claude (claude.ai web + Desktop custom connectors)
            '#^https://claude\.(ai|com)/api/mcp/auth_callback$#',
            // VS Code web redirect
            '#^https://(insiders\.)?vscode\.dev/redirect$#',
            // Loopback for desktop clients (Claude Code, mcp-remote, VS Code, inspector)
            '#^http://(localhost|127\.0\.0\.1|\[::1\])(:\d{1,5})?(/[A-Za-z0-9._~\-/%]*)?$#',
            // Native app schemes
            '#^(cursor|vscode|vscode-insiders|claude)://[A-Za-z0-9._~\-/%:]+$#',
        ];

        foreach ($patterns as $re) {
            if (preg_match($re, $uri) === 1) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> internal scopes (read / write) */
    public static function parseScopes(?string $scope): array
    {
        $out = [];
        foreach (preg_split('/[\s,]+/', (string) $scope) ?: [] as $s) {
            $s = strtolower(trim($s));
            if (in_array($s, ['mcp:read', 'read'], true)) {
                $out[] = 'read';
            } elseif (in_array($s, ['mcp:write', 'write'], true)) {
                $out[] = 'write';
            }
        }

        return array_values(array_unique($out));
    }

    public static function pkceMatches(string $verifier, string $challenge): bool
    {
        if (preg_match('/^[A-Za-z0-9\-._~]{43,128}$/', $verifier) !== 1) {
            return false;
        }
        $computed = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        return hash_equals($challenge, $computed);
    }

    /** Accepts "https://host/mcp", "https://host/mcp/" or "https://host" as the resource indicator. */
    public static function resourceMatches(?string $resource, Request $request): bool
    {
        if ($resource === null || $resource === '') {
            return true;
        }
        $r = rtrim($resource, '/');

        return $r === self::resource($request) || $r === self::issuer($request);
    }
}
