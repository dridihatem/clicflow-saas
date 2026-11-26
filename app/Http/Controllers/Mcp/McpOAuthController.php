<?php

namespace App\Http\Controllers\Mcp;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\McpOAuthClient;
use App\Models\McpOAuthCode;
use App\Models\McpToken;
use App\Models\User;
use App\Services\Mcp\McpOAuthMetadata as Meta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * OAuth 2.1 authorization server for the workspace MCP (ChatGPT / Claude custom connectors).
 * Issued access tokens are regular McpToken rows (listed + revocable in /settings/mcp).
 */
class McpOAuthController extends Controller
{
    // ---------------------------------------------------------------- discovery

    public function protectedResource(Request $request): JsonResponse
    {
        return $this->json(Meta::protectedResource($request));
    }

    public function authorizationServer(Request $request): JsonResponse
    {
        return $this->json(Meta::authorizationServer($request));
    }

    // ---------------------------------------------------------------- RFC 7591 registration

    public function register(Request $request): JsonResponse
    {
        $data = $request->json()->all() ?: $request->all();
        $uris = $data['redirect_uris'] ?? null;
        if (! is_array($uris) || $uris === [] || count($uris) > 10) {
            return $this->oauthError('invalid_redirect_uri', 'redirect_uris must be a non-empty array.', 400);
        }
        $uris = array_values(array_unique(array_map(fn ($u) => is_string($u) ? trim($u) : '', $uris)));
        foreach ($uris as $u) {
            if (! Meta::redirectUriAllowed($u)) {
                return $this->oauthError('invalid_redirect_uri', 'Redirect URI not allowed: '.mb_substr($u, 0, 200), 400);
            }
        }

        $grants = $data['grant_types'] ?? ['authorization_code', 'refresh_token'];
        if (! is_array($grants) || array_diff($grants, ['authorization_code', 'refresh_token']) !== []) {
            return $this->oauthError('invalid_client_metadata', 'Only authorization_code and refresh_token grants are supported.', 400);
        }
        $responseTypes = $data['response_types'] ?? ['code'];
        if (! is_array($responseTypes) || array_diff($responseTypes, ['code']) !== []) {
            return $this->oauthError('invalid_client_metadata', 'Only the "code" response type is supported.', 400);
        }
        $method = (string) ($data['token_endpoint_auth_method'] ?? 'client_secret_basic');
        if (! in_array($method, ['none', 'client_secret_post', 'client_secret_basic'], true)) {
            return $this->oauthError('invalid_client_metadata', 'Unsupported token_endpoint_auth_method.', 400);
        }

        $name = trim(strip_tags((string) ($data['client_name'] ?? ''))) ?: 'Client MCP';
        $clientUri = is_string($data['client_uri'] ?? null) && str_starts_with($data['client_uri'], 'https://') ? mb_substr($data['client_uri'], 0, 255) : null;
        $clientId = 'cfc_'.Str::lower(Str::random(32));
        $secret = $method === 'none' ? null : 'cfs_'.Str::random(48);

        $client = McpOAuthClient::query()->create([
            'client_id' => $clientId,
            'client_secret_hash' => $secret ? hash('sha256', $secret) : null,
            'name' => mb_substr($name, 0, 120),
            'redirect_uris' => $uris,
            'token_endpoint_auth_method' => $method,
            'client_uri' => $clientUri,
            'created_ip' => $request->ip(),
        ]);

        $out = [
            'client_id' => $client->client_id,
            'client_id_issued_at' => $client->created_at->getTimestamp(),
            'client_name' => $client->name,
            'redirect_uris' => $uris,
            'grant_types' => array_values($grants),
            'response_types' => ['code'],
            'token_endpoint_auth_method' => $method,
            'scope' => implode(' ', Meta::SCOPES),
        ];
        if ($secret) {
            $out['client_secret'] = $secret;
            $out['client_secret_expires_at'] = 0;
        }

        return $this->json($out, 201);
    }

    // ---------------------------------------------------------------- authorize + consent

    public function authorize(Request $request): View|RedirectResponse
    {
        $client = McpOAuthClient::query()->where('client_id', (string) $request->query('client_id', ''))->first();
        if (! $client instanceof McpOAuthClient) {
            return $this->errorPage(__('mcp_oauth_err_unknown_client'));
        }
        $redirect = (string) $request->query('redirect_uri', '');
        if ($redirect === '' && count($client->redirect_uris ?? []) === 1) {
            $redirect = $client->redirect_uris[0];
        }
        if (! $client->hasRedirectUri($redirect)) {
            return $this->errorPage(__('mcp_oauth_err_redirect'));
        }

        $state = $request->query('state');
        $state = is_string($state) ? mb_substr($state, 0, 1000) : null;
        $back = fn (string $error, string $desc) => $this->redirectWith($request, $redirect, ['error' => $error, 'error_description' => $desc, 'state' => $state]);

        if ($request->query('response_type') !== 'code') {
            return $back('unsupported_response_type', 'response_type must be "code".');
        }
        $challenge = (string) $request->query('code_challenge', '');
        if ($request->query('code_challenge_method') !== 'S256' || preg_match('/^[A-Za-z0-9_\-]{43,128}$/', $challenge) !== 1) {
            return $back('invalid_request', 'PKCE with code_challenge_method=S256 is required.');
        }
        $resource = $request->query('resource');
        $resource = is_string($resource) ? $resource : null;
        if (! Meta::resourceMatches($resource, $request)) {
            return $back('invalid_target', 'Unknown resource for this authorization server.');
        }

        $requested = Meta::parseScopes((string) $request->query('scope', ''));
        if ($requested === []) {
            $requested = ['read', 'write'];
        }

        $user = $request->user();
        if (! $user instanceof User) {
            $request->session()->put('mcp_oauth_return', $request->getRequestUri());

            return redirect()->guest(route('login'))->with('status', __('mcp_oauth_login_notice', ['app' => $client->name]));
        }

        $company = $this->companyFor($request, $user);
        if (! $company instanceof Company) {
            return $this->errorPage(__('mcp_oauth_err_workspace'), true);
        }
        if (! $user->hasRole('company_admin')) {
            return $this->errorPage(__('mcp_oauth_err_admin'), true);
        }

        $rid = Str::random(40);
        $request->session()->put('mcp_oauth_req.'.$rid, [
            'client_id' => $client->client_id,
            'redirect_uri' => $redirect,
            'state' => $state,
            'code_challenge' => $challenge,
            'scopes' => $requested,
            'resource' => $resource ? rtrim($resource, '/') : Meta::resource($request),
            'issuer' => Meta::issuer($request),
            'user_id' => $user->id,
            'company_id' => (string) $company->id,
            'at' => now()->getTimestamp(),
        ]);

        return view('mcp.oauth.authorize', [
            'client' => $client,
            'company' => $company,
            'user' => $user,
            'rid' => $rid,
            'requested' => $requested,
            'redirectHost' => parse_url($redirect, PHP_URL_HOST) ?: parse_url($redirect, PHP_URL_SCHEME),
            'endpoint' => Meta::resource($request),
            'serverName' => Meta::displayName($company),
        ]);
    }

    public function approve(Request $request): RedirectResponse|View
    {
        $rid = (string) $request->input('rid', '');
        $pending = $rid !== '' ? $request->session()->pull('mcp_oauth_req.'.$rid) : null;
        $user = $request->user();
        if (! is_array($pending) || ! $user instanceof User || (string) $pending['user_id'] !== (string) $user->id
            || now()->getTimestamp() - (int) $pending['at'] > 1800) {
            return $this->errorPage(__('mcp_oauth_err_expired'));
        }
        $client = McpOAuthClient::query()->where('client_id', $pending['client_id'])->first();
        if (! $client instanceof McpOAuthClient || ! $client->hasRedirectUri($pending['redirect_uri'])) {
            return $this->errorPage(__('mcp_oauth_err_unknown_client'));
        }
        $company = $this->companyFor($request, $user);
        if (! $company instanceof Company || (string) $company->id !== (string) $pending['company_id'] || ! $user->hasRole('company_admin')) {
            return $this->errorPage(__('mcp_oauth_err_workspace'), true);
        }

        if ($request->input('decision') !== 'approve') {
            return $this->redirectWith($request, $pending['redirect_uri'], ['error' => 'access_denied', 'error_description' => 'The user denied access.', 'state' => $pending['state']]);
        }

        $scopes = array_values(array_intersect(
            array_map('strval', (array) $request->input('scopes', [])),
            $pending['scopes'],
        ));
        if ($scopes === []) {
            $scopes = ['read'];
        }

        $plain = Str::random(64);
        McpOAuthCode::query()->create([
            'code_hash' => hash('sha256', $plain),
            'client_id' => $client->client_id,
            'company_id' => $pending['company_id'],
            'user_id' => $user->id,
            'redirect_uri' => $pending['redirect_uri'],
            'code_challenge' => $pending['code_challenge'],
            'scopes' => $scopes,
            'resource' => $pending['resource'],
            'issuer' => $pending['issuer'],
            'expires_at' => now()->addSeconds(Meta::CODE_TTL),
        ]);

        return $this->redirectWith($request, $pending['redirect_uri'], ['code' => $plain, 'state' => $pending['state']]);
    }

    // ---------------------------------------------------------------- token

    public function token(Request $request): JsonResponse
    {
        [$client, $err] = $this->authenticateClient($request);
        if ($err) {
            return $err;
        }

        return match ((string) $request->input('grant_type', '')) {
            'authorization_code' => $this->grantCode($request, $client),
            'refresh_token' => $this->grantRefresh($request, $client),
            default => $this->oauthError('unsupported_grant_type', 'Supported grants: authorization_code, refresh_token.'),
        };
    }

    private function grantCode(Request $request, McpOAuthClient $client): JsonResponse
    {
        $plain = (string) $request->input('code', '');
        $code = $plain !== '' ? McpOAuthCode::query()->where('code_hash', hash('sha256', $plain))->first() : null;
        if (! $code instanceof McpOAuthCode || $code->client_id !== $client->client_id) {
            return $this->oauthError('invalid_grant', 'Invalid authorization code.');
        }
        if ($code->used_at !== null) {
            // Replay: revoke whatever the first exchange produced.
            if ($code->mcp_token_id) {
                McpToken::query()->whereKey($code->mcp_token_id)->first()?->revoke();
            }

            return $this->oauthError('invalid_grant', 'Authorization code already used.');
        }
        if ($code->expires_at->isPast()) {
            return $this->oauthError('invalid_grant', 'Authorization code expired.');
        }
        $redirect = (string) $request->input('redirect_uri', '');
        if ($redirect !== '' && $redirect !== $code->redirect_uri) {
            return $this->oauthError('invalid_grant', 'redirect_uri mismatch.');
        }
        if (! Meta::pkceMatches((string) $request->input('code_verifier', ''), $code->code_challenge)) {
            return $this->oauthError('invalid_grant', 'PKCE verification failed.');
        }
        if ($code->issuer !== Meta::issuer($request) || ! $this->hostAllowsCompany((string) $code->company_id)) {
            return $this->oauthError('invalid_grant', 'Authorization code was issued for another server.');
        }
        $resource = $request->input('resource');
        if (is_string($resource) && $resource !== '' && rtrim($resource, '/') !== rtrim((string) $code->resource, '/') && ! Meta::resourceMatches($resource, $request)) {
            return $this->oauthError('invalid_target', 'resource mismatch.');
        }

        $claimed = McpOAuthCode::query()->whereKey($code->id)->whereNull('used_at')->update(['used_at' => now()]);
        if ($claimed !== 1) {
            return $this->oauthError('invalid_grant', 'Authorization code already used.');
        }

        $access = 'cfm_'.Str::lower(Str::random(48));
        $refresh = 'cfr_'.Str::random(64);
        $token = McpToken::query()->create([
            'tokenable_type' => McpToken::AUDIENCE_COMPANY,
            'tokenable_id' => (string) $code->company_id,
            'name' => mb_substr($client->name.' (OAuth) · '.Meta::displayName(Company::query()->withoutGlobalScopes()->find($code->company_id)), 0, 120),
            'token_prefix' => substr($access, 0, 10),
            'token_hash' => hash('sha256', $access),
            'scopes' => $code->scopes,
            'oauth_client_id' => $client->client_id,
            'user_id' => $code->user_id,
            'expires_at' => now()->addSeconds(Meta::ACCESS_TTL),
            'refresh_token_hash' => hash('sha256', $refresh),
            'refresh_expires_at' => now()->addDays(Meta::REFRESH_TTL_DAYS),
            'oauth_resource' => rtrim((string) ($code->resource ?: Meta::resource($request)), '/'),
        ]);
        $code->forceFill(['mcp_token_id' => $token->id])->save();
        $client->forceFill(['last_used_at' => now()])->saveQuietly();

        return $this->tokenResponse($token, $access, $refresh);
    }

    private function grantRefresh(Request $request, McpOAuthClient $client): JsonResponse
    {
        $plain = (string) $request->input('refresh_token', '');
        if ($plain === '') {
            return $this->oauthError('invalid_request', 'refresh_token is required.');
        }
        $hash = hash('sha256', $plain);

        return DB::transaction(function () use ($request, $client, $hash) {
            $token = McpToken::query()->where('refresh_token_hash', $hash)->lockForUpdate()->first();
            $grace = false;
            if (! $token instanceof McpToken) {
                // A concurrent refresh just rotated it: allow a short grace window (no new refresh token).
                $token = McpToken::query()->where('refresh_prev_hash', $hash)
                    ->where('refresh_rotated_at', '>', now()->subSeconds(120))->lockForUpdate()->first();
                $grace = $token instanceof McpToken;
            }
            if (! $token instanceof McpToken || $token->revoked_at !== null || $token->oauth_client_id !== $client->client_id
                || ($token->refresh_expires_at && $token->refresh_expires_at->isPast())) {
                return $this->oauthError('invalid_grant', 'Invalid or revoked refresh token.');
            }
            if (! $this->hostAllowsCompany((string) $token->tokenable_id)
                || ($token->oauth_resource && rtrim($token->oauth_resource, '/') !== Meta::resource($request))) {
                return $this->oauthError('invalid_grant', 'Refresh token was issued for another server.');
            }

            $access = 'cfm_'.Str::lower(Str::random(48));
            $fill = [
                'token_prefix' => substr($access, 0, 10),
                'token_hash' => hash('sha256', $access),
                'expires_at' => now()->addSeconds(Meta::ACCESS_TTL),
            ];
            $refresh = null;
            if (! $grace) {
                $refresh = 'cfr_'.Str::random(64);
                $fill += [
                    'refresh_prev_hash' => $token->refresh_token_hash,
                    'refresh_rotated_at' => now(),
                    'refresh_token_hash' => hash('sha256', $refresh),
                    'refresh_expires_at' => now()->addDays(Meta::REFRESH_TTL_DAYS),
                ];
            }
            $token->forceFill($fill)->save();
            $client->forceFill(['last_used_at' => now()])->saveQuietly();

            return $this->tokenResponse($token, $access, $refresh);
        });
    }

    public function revoke(Request $request): JsonResponse
    {
        [$client, $err] = $this->authenticateClient($request);
        if ($err) {
            return $err;
        }
        $plain = (string) $request->input('token', '');
        if ($plain !== '') {
            $hash = hash('sha256', $plain);
            $token = McpToken::query()->where('oauth_client_id', $client->client_id)
                ->where(fn ($q) => $q->where('token_hash', $hash)->orWhere('refresh_token_hash', $hash))
                ->first();
            $token?->revoke();
        }

        return $this->json([], 200);
    }

    // ---------------------------------------------------------------- helpers

    /** @return array{0: ?McpOAuthClient, 1: ?JsonResponse} */
    private function authenticateClient(Request $request): array
    {
        $id = (string) $request->input('client_id', '');
        $secret = $request->input('client_secret');
        $basicUser = $request->getUser();
        if (is_string($basicUser) && $basicUser !== '') {
            $id = urldecode($basicUser);
            $secret = urldecode((string) $request->getPassword());
        }
        $client = $id !== '' ? McpOAuthClient::query()->where('client_id', $id)->first() : null;
        if (! $client instanceof McpOAuthClient || ! $client->secretMatches(is_string($secret) ? $secret : null)) {
            return [null, $this->oauthError('invalid_client', 'Client authentication failed.', 401)];
        }

        return [$client, null];
    }

    private function companyFor(Request $request, User $user): ?Company
    {
        $company = $user->company;
        if (! $company instanceof Company || ! $company->is_enabled) {
            return null;
        }
        $current = app()->bound('currentCompany') ? app('currentCompany') : null;
        if ($current instanceof Company && (string) $current->id !== (string) $company->id) {
            return null;
        }

        return $company;
    }

    private function hostAllowsCompany(string $companyId): bool
    {
        $current = app()->bound('currentCompany') ? app('currentCompany') : null;

        return ! $current instanceof Company || (string) $current->id === $companyId;
    }

    private function tokenResponse(McpToken $token, string $access, ?string $refresh): JsonResponse
    {
        $out = [
            'access_token' => $access,
            'token_type' => 'Bearer',
            'expires_in' => Meta::ACCESS_TTL,
            'scope' => $token->oauthScopeString(),
        ];
        if ($refresh !== null) {
            $out['refresh_token'] = $refresh;
        }

        return $this->json($out);
    }

    /** @param array<string, mixed> $params */
    private function redirectWith(Request $request, string $uri, array $params): RedirectResponse
    {
        $params['iss'] = Meta::issuer($request);
        $params = array_filter($params, fn ($v) => $v !== null && $v !== '');
        $sep = str_contains($uri, '?') ? '&' : '?';

        return redirect()->away($uri.$sep.http_build_query($params, '', '&', PHP_QUERY_RFC3986));
    }

    private function errorPage(string $message, bool $offerSwitch = false): View
    {
        return view('mcp.oauth.error', ['message' => $message, 'offerSwitch' => $offerSwitch && Auth::check()]);
    }

    private function oauthError(string $error, string $description, int $status = 400): JsonResponse
    {
        return $this->json(['error' => $error, 'error_description' => $description], $status);
    }

    /** @param array<string, mixed> $data */
    private function json(array $data, int $status = 200): JsonResponse
    {
        return response()->json($data === [] ? (object) [] : $data, $status, [
            'Cache-Control' => 'no-store',
            'Pragma' => 'no-cache',
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
