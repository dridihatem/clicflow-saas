<?php

use App\Models\Company;
use App\Models\McpOAuthClient;
use App\Models\McpToken;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    config(['flowdesk.tenant_base_domain' => 'clicflow.fr']);
});

function oauthCompany(string $name = 'Atelier OAuth'): Company
{
    return Company::factory()->create([
        'name' => $name,
        'subdomain' => 'oauth-'.substr(uniqid(), -7),
        'contact_email' => 'contact@oauth.test',
    ]);
}

function oauthBase(Company $c): string
{
    return 'http://'.$c->subdomain.'.clicflow.fr';
}

function oauthAdmin(Company $c, string $role = 'company_admin'): User
{
    $u = User::factory()->create(['company_id' => $c->id]);
    $u->assignRole($role);

    return $u;
}

/** @return array{0: string, 1: string} verifier, challenge */
function oauthPkce(): array
{
    $verifier = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

    return [$verifier, $challenge];
}

function oauthRegister(Company $c, array $extra = []): array
{
    return test()->postJson(oauthBase($c).'/mcp/oauth/register', array_merge([
        'client_name' => 'Claude',
        'redirect_uris' => ['https://claude.ai/api/mcp/auth_callback'],
        'token_endpoint_auth_method' => 'none',
        'grant_types' => ['authorization_code', 'refresh_token'],
        'response_types' => ['code'],
    ], $extra))->assertCreated()->json();
}

/** Full authorize + consent; returns the code. */
function oauthCode(Company $c, User $u, array $client, string $challenge, array $scopes = ['read', 'write'], ?string $redirect = null): string
{
    $redirect ??= $client['redirect_uris'][0];
    $page = test()->actingAs($u)->get(oauthBase($c).'/mcp/oauth/authorize?'.http_build_query([
        'response_type' => 'code',
        'client_id' => $client['client_id'],
        'redirect_uri' => $redirect,
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
        'scope' => 'mcp:read mcp:write',
        'state' => 'xyz',
        'resource' => oauthBase($c).'/mcp',
    ]));
    $page->assertOk()->assertSee('data-mcp-oauth-consent', false)->assertSee($c->name);
    preg_match('/name="rid" value="([^"]+)"/', $page->getContent(), $m);

    $res = test()->actingAs($u)->post(oauthBase($c).'/mcp-oauth/approve', ['rid' => $m[1], 'decision' => 'approve', 'scopes' => $scopes]);
    $res->assertRedirect();
    $loc = $res->headers->get('Location');
    expect($loc)->toStartWith($redirect.'?');
    parse_str(parse_url($loc, PHP_URL_QUERY), $q);
    expect($q['state'])->toBe('xyz')->and($q['iss'])->toBe(oauthBase($c));

    return $q['code'];
}

function oauthExchange(Company $c, array $client, string $code, string $verifier)
{
    return test()->post(oauthBase($c).'/mcp/oauth/token', [
        'grant_type' => 'authorization_code',
        'code' => $code,
        'redirect_uri' => $client['redirect_uris'][0],
        'client_id' => $client['client_id'],
        'code_verifier' => $verifier,
        'resource' => oauthBase($c).'/mcp',
    ]);
}

function oauthMcp(Company $c, string $access, array $payload, ?string $host = null)
{
    return test()->postJson(($host ?? oauthBase($c)).'/mcp', $payload, ['Authorization' => 'Bearer '.$access, 'Accept' => 'application/json, text/event-stream']);
}

test('discovery metadata and 401 challenge', function () {
    $c = oauthCompany();
    $base = oauthBase($c);

    $this->getJson($base.'/.well-known/oauth-protected-resource/mcp')->assertOk()
        ->assertJsonPath('resource', $base.'/mcp')
        ->assertJsonPath('authorization_servers.0', $base);
    $this->getJson($base.'/.well-known/oauth-protected-resource')->assertOk()->assertJsonPath('resource', $base.'/mcp');

    $as = $this->getJson($base.'/.well-known/oauth-authorization-server')->assertOk();
    $as->assertJsonPath('issuer', $base)
        ->assertJsonPath('token_endpoint', $base.'/mcp/oauth/token')
        ->assertJsonPath('registration_endpoint', $base.'/mcp/oauth/register')
        ->assertJsonPath('code_challenge_methods_supported', ['S256']);
    $this->getJson($base.'/.well-known/oauth-authorization-server/mcp')->assertOk()->assertJsonPath('issuer', $base);

    $r = $this->postJson($base.'/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize']);
    $r->assertStatus(401);
    expect($r->headers->get('WWW-Authenticate'))
        ->toContain('Bearer')
        ->toContain('resource_metadata="'.$base.'/.well-known/oauth-protected-resource/mcp"');

    // Streamable HTTP: no server-initiated SSE stream on GET.
    $this->get($base.'/mcp', ['Accept' => 'text/event-stream'])->assertStatus(405);
});

test('dynamic client registration validates redirect uris strictly', function () {
    $c = oauthCompany();
    $public = oauthRegister($c);
    expect($public['client_id'])->toStartWith('cfc_')->and($public)->not->toHaveKey('client_secret');

    $conf = oauthRegister($c, ['client_name' => 'ChatGPT', 'redirect_uris' => ['https://chatgpt.com/connector_platform_oauth_redirect'], 'token_endpoint_auth_method' => 'client_secret_post']);
    expect($conf['client_secret'])->toStartWith('cfs_');
    expect(McpOAuthClient::where('client_id', $conf['client_id'])->value('client_secret_hash'))->toBe(hash('sha256', $conf['client_secret']));

    oauthRegister($c, ['redirect_uris' => ['http://localhost:6274/oauth/callback', 'https://claude.com/api/mcp/auth_callback']]);

    foreach (['https://evil.example/cb', 'https://chatgpt.com.evil.io/connector_platform_oauth_redirect', 'http://claude.ai/api/mcp/auth_callback', 'https://claude.ai/api/mcp/auth_callback#x', 'javascript:alert(1)'] as $bad) {
        $this->postJson(oauthBase($c).'/mcp/oauth/register', ['client_name' => 'x', 'redirect_uris' => [$bad], 'token_endpoint_auth_method' => 'none'])
            ->assertStatus(400)->assertJsonPath('error', 'invalid_redirect_uri');
    }
});

test('authorize requires PKCE S256 and an exact registered redirect uri', function () {
    $c = oauthCompany();
    $u = oauthAdmin($c);
    $client = oauthRegister($c);
    $base = oauthBase($c);

    $plain = $this->actingAs($u)->get($base.'/mcp/oauth/authorize?'.http_build_query([
        'response_type' => 'code', 'client_id' => $client['client_id'], 'redirect_uri' => $client['redirect_uris'][0], 'state' => 's',
    ]));
    $plain->assertRedirect();
    expect($plain->headers->get('Location'))->toContain('error=invalid_request');

    $plainMethod = $this->actingAs($u)->get($base.'/mcp/oauth/authorize?'.http_build_query([
        'response_type' => 'code', 'client_id' => $client['client_id'], 'redirect_uri' => $client['redirect_uris'][0],
        'code_challenge' => str_repeat('a', 43), 'code_challenge_method' => 'plain',
    ]));
    expect($plainMethod->headers->get('Location'))->toContain('error=invalid_request');

    // Unregistered redirect: never redirect, show error page.
    $this->actingAs($u)->get($base.'/mcp/oauth/authorize?'.http_build_query([
        'response_type' => 'code', 'client_id' => $client['client_id'], 'redirect_uri' => 'https://claude.ai/api/mcp/other',
        'code_challenge' => str_repeat('a', 43), 'code_challenge_method' => 'S256',
    ]))->assertOk()->assertSee('data-mcp-oauth-error', false);
});

test('guest is sent to login then back to the consent page', function () {
    $c = oauthCompany();
    $client = oauthRegister($c);
    [, $challenge] = oauthPkce();
    $url = '/mcp/oauth/authorize?'.http_build_query(['response_type' => 'code', 'client_id' => $client['client_id'], 'redirect_uri' => $client['redirect_uris'][0], 'code_challenge' => $challenge, 'code_challenge_method' => 'S256']);
    $this->get(oauthBase($c).$url)->assertRedirect();
    expect(session('mcp_oauth_return'))->toBe($url);

    $u = oauthAdmin($c);
    $req = \Illuminate\Http\Request::create(oauthBase($c).'/login');
    $req->setLaravelSession(app('session.store'));
    $redirect = flowdesk_redirect_after_auth($u, $req);
    expect($redirect->getTargetUrl())->toEndWith($url);
});

test('full flow: code exchange, MCP call, refresh rotation, revoke', function () {
    $c = oauthCompany();
    $u = oauthAdmin($c);
    $client = oauthRegister($c);
    [$verifier, $challenge] = oauthPkce();
    $code = oauthCode($c, $u, $client, $challenge);

    // Wrong verifier fails (and does not burn the code).
    oauthExchange($c, $client, $code, str_repeat('b', 50))->assertStatus(400)->assertJsonPath('error', 'invalid_grant');

    $tok = oauthExchange($c, $client, $code, $verifier)->assertOk()->json();
    expect($tok['token_type'])->toBe('Bearer')->and($tok['expires_in'])->toBe(3600)->and($tok['scope'])->toBe('mcp:read mcp:write');

    // Replay revokes the issued token.
    oauthExchange($c, $client, $code, $verifier)->assertStatus(400);
    oauthMcp($c, $tok['access_token'], ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])->assertStatus(401);

    // New authorization works.
    [$verifier, $challenge] = oauthPkce();
    $tok = oauthExchange($c, $client, oauthCode($c, $u, $client, $challenge), $verifier)->assertOk()->json();
    oauthMcp($c, $tok['access_token'], ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => ['protocolVersion' => '2025-06-18']])
        ->assertOk()->assertJsonPath('result.protocolVersion', '2025-06-18');
    oauthMcp($c, $tok['access_token'], ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list'])->assertOk();

    $row = McpToken::where('token_hash', hash('sha256', $tok['access_token']))->first();
    expect($row->isOAuth())->toBeTrue()->and($row->name)->toBe('Claude (OAuth) · Atelier OAuth - ClicFlow')->and($row->tokenable_id)->toBe((string) $c->id);
    expect($row->refresh_token_hash)->toBe(hash('sha256', $tok['refresh_token']))->and($row->refresh_token_hash)->not->toBe($tok['refresh_token']);

    // Listed in /settings/mcp with the OAuth badge.
    $this->actingAs($u)->get(oauthBase($c).'/settings/mcp')->assertOk()->assertSee('Claude (OAuth)')->assertSee('data-mcp-oauth-badge', false);

    $new = $this->post(oauthBase($c).'/mcp/oauth/token', ['grant_type' => 'refresh_token', 'refresh_token' => $tok['refresh_token'], 'client_id' => $client['client_id']])->assertOk()->json();
    expect($new['refresh_token'])->not->toBe($tok['refresh_token']);
    oauthMcp($c, $tok['access_token'], ['jsonrpc' => '2.0', 'id' => 3, 'method' => 'tools/list'])->assertStatus(401);
    oauthMcp($c, $new['access_token'], ['jsonrpc' => '2.0', 'id' => 3, 'method' => 'tools/list'])->assertOk();

    // Old refresh within grace: new access token but no new refresh token.
    $grace = $this->post(oauthBase($c).'/mcp/oauth/token', ['grant_type' => 'refresh_token', 'refresh_token' => $tok['refresh_token'], 'client_id' => $client['client_id']])->assertOk()->json();
    expect($grace)->not->toHaveKey('refresh_token');

    // Expired access token is rejected.
    McpToken::whereKey($row->id)->update(['expires_at' => now()->subMinute()]);
    oauthMcp($c, $grace['access_token'], ['jsonrpc' => '2.0', 'id' => 4, 'method' => 'tools/list'])->assertStatus(401);

    // Revocation from /settings/mcp kills access + refresh.
    $this->actingAs($u)->delete(oauthBase($c).'/settings/mcp/'.$row->id)->assertRedirect();
    $this->post(oauthBase($c).'/mcp/oauth/token', ['grant_type' => 'refresh_token', 'refresh_token' => $new['refresh_token'], 'client_id' => $client['client_id']])
        ->assertStatus(400)->assertJsonPath('error', 'invalid_grant');
});

test('revocation endpoint and confidential client authentication', function () {
    $c = oauthCompany();
    $u = oauthAdmin($c);
    $client = oauthRegister($c, ['client_name' => 'ChatGPT', 'redirect_uris' => ['https://chatgpt.com/connector_platform_oauth_redirect'], 'token_endpoint_auth_method' => 'client_secret_basic']);
    [$verifier, $challenge] = oauthPkce();
    $code = oauthCode($c, $u, $client, $challenge, ['read']);

    $this->post(oauthBase($c).'/mcp/oauth/token', ['grant_type' => 'authorization_code', 'code' => $code, 'client_id' => $client['client_id'], 'code_verifier' => $verifier])
        ->assertStatus(401)->assertJsonPath('error', 'invalid_client');

    $basic = ['Authorization' => 'Basic '.base64_encode($client['client_id'].':'.$client['client_secret'])];
    $tok = $this->post(oauthBase($c).'/mcp/oauth/token', ['grant_type' => 'authorization_code', 'code' => $code, 'code_verifier' => $verifier, 'redirect_uri' => $client['redirect_uris'][0]], $basic)->assertOk()->json();
    expect($tok['scope'])->toBe('mcp:read');

    $this->post(oauthBase($c).'/mcp/oauth/revoke', ['token' => $tok['access_token']], $basic)->assertOk();
    oauthMcp($c, $tok['access_token'], ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])->assertStatus(401);
});

test('scope enforcement: mcp:read cannot call write tools', function () {
    $c = oauthCompany();
    $u = oauthAdmin($c);
    $client = oauthRegister($c);
    [$verifier, $challenge] = oauthPkce();
    $tok = oauthExchange($c, $client, oauthCode($c, $u, $client, $challenge, ['read']), $verifier)->assertOk()->json();
    expect($tok['scope'])->toBe('mcp:read');

    oauthMcp($c, $tok['access_token'], ['jsonrpc' => '2.0', 'id' => 5, 'method' => 'tools/call', 'params' => ['name' => 'create_client', 'arguments' => ['name' => 'X', 'email' => 'x@x.test']]])
        ->assertOk()->assertJsonPath('error.code', -32003);
    oauthMcp($c, $tok['access_token'], ['jsonrpc' => '2.0', 'id' => 6, 'method' => 'tools/call', 'params' => ['name' => 'list_clients', 'arguments' => []]])
        ->assertOk()->assertJsonMissingPath('error');
});

test('tenant isolation: tokens, codes and consent are bound to their workspace', function () {
    $a = oauthCompany('Alpha');
    $b = oauthCompany('Beta');
    $ua = oauthAdmin($a);
    $client = oauthRegister($a);
    [$verifier, $challenge] = oauthPkce();
    $code = oauthCode($a, $ua, $client, $challenge);

    // Code from A cannot be redeemed on B's host.
    oauthExchange($b, $client, $code, $verifier)->assertStatus(400);
    $tok = oauthExchange($a, $client, $code, $verifier)->assertOk()->json();

    // Token from A rejected on B and on app host (audience bound).
    oauthMcp($a, $tok['access_token'], ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'], oauthBase($b))->assertStatus(401);
    oauthMcp($a, $tok['access_token'], ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'], 'http://localhost')->assertStatus(401);

    // A user of A cannot authorize on B's host.
    $this->actingAs($ua)->get(oauthBase($b).'/mcp/oauth/authorize?'.http_build_query([
        'response_type' => 'code', 'client_id' => $client['client_id'], 'redirect_uri' => $client['redirect_uris'][0], 'code_challenge' => $challenge, 'code_challenge_method' => 'S256',
    ]))->assertOk()->assertSee('data-mcp-oauth-error', false);

    // Non-admin staff cannot authorize.
    $staff = User::factory()->create(['company_id' => $a->id]);
    $staff->syncRoles(['team_member']);
    $this->actingAs($staff)->get(oauthBase($a).'/mcp/oauth/authorize?'.http_build_query([
        'response_type' => 'code', 'client_id' => $client['client_id'], 'redirect_uri' => $client['redirect_uris'][0], 'code_challenge' => $challenge, 'code_challenge_method' => 'S256',
    ]))->assertOk()->assertSee('data-mcp-oauth-error', false);
});

test('deny returns access_denied and legacy bearer keys still work', function () {
    $c = oauthCompany();
    $u = oauthAdmin($c);
    $client = oauthRegister($c);
    [, $challenge] = oauthPkce();
    $page = $this->actingAs($u)->get(oauthBase($c).'/mcp/oauth/authorize?'.http_build_query([
        'response_type' => 'code', 'client_id' => $client['client_id'], 'redirect_uri' => $client['redirect_uris'][0], 'code_challenge' => $challenge, 'code_challenge_method' => 'S256', 'state' => 'q',
    ]));
    preg_match('/name="rid" value="([^"]+)"/', $page->getContent(), $m);
    $deny = $this->actingAs($u)->post(oauthBase($c).'/mcp-oauth/approve', ['rid' => $m[1], 'decision' => 'deny']);
    expect($deny->headers->get('Location'))->toContain('error=access_denied')->toContain('state=q');

    $legacy = McpToken::issue(McpToken::AUDIENCE_COMPANY, $c->id, 'Cursor', ['read']);
    oauthMcp($c, $legacy['plain'], ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize'])->assertOk()->assertJsonPath('result.protocolVersion', '2025-03-26');
    oauthMcp($c, $legacy['plain'], ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list'], 'http://localhost')->assertOk();
});

test('server display name is "<Company> - ClicFlow" and the MCP page embeds the guide', function () {
    $c = oauthCompany('Studio Nova');
    $u = oauthAdmin($c);
    $base = oauthBase($c);

    $this->getJson($base.'/.well-known/oauth-protected-resource/mcp')->assertJsonPath('resource_name', 'Studio Nova - ClicFlow');
    $legacy = McpToken::issue(McpToken::AUDIENCE_COMPANY, $c->id, 'k', ['read']);
    oauthMcp($c, $legacy['plain'], ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => ['protocolVersion' => '2025-06-18']])
        ->assertOk()->assertJsonPath('result.serverInfo.name', 'Studio Nova - ClicFlow')->assertJsonPath('result.serverInfo.title', 'Studio Nova - ClicFlow');

    $client = oauthRegister($c);
    [, $challenge] = oauthPkce();
    $this->actingAs($u)->get($base.'/mcp/oauth/authorize?'.http_build_query([
        'response_type' => 'code', 'client_id' => $client['client_id'], 'redirect_uri' => $client['redirect_uris'][0], 'code_challenge' => $challenge, 'code_challenge_method' => 'S256',
    ]))->assertOk()->assertSee('Studio Nova - ClicFlow');

    $page = $this->actingAs($u)->get($base.'/settings/mcp')->assertOk();
    $page->assertSee('data-mcp-guide', false)
        ->assertSee($c->subdomain.'.clicflow.fr/mcp', false)
        ->assertSee('Studio Nova - ClicFlow')
        ->assertSee('claude mcp add --transport http studio-nova-clicflow', false)
        ->assertSee('data-guide-tab="help"', false);
    // Fallback name without company.
    app()->forgetInstance('currentCompany');
    expect(\App\Services\Mcp\McpOAuthMetadata::displayName(null))->toBe('ClicFlow');
});
