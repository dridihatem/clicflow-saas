<?php

use App\Models\Client;
use App\Models\Company;
use App\Models\McpToken;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    config(['flowdesk.tenant_base_domain' => 'clicflow.fr']);
    RateLimiter::clear('mcp-test');
});

function mcpCompany(array $attrs = []): Company
{
    return Company::factory()->create(array_merge([
        'name' => 'Atelier MCP',
        'subdomain' => 'atelier-mcp-'.substr(uniqid(), -6),
        'contact_email' => 'contact@atelier-mcp.test',
    ], $attrs));
}

function mcpPost(Company $company, string $plain, array $payload)
{
    return test()->postJson(
        'http://'.$company->subdomain.'.clicflow.fr/mcp',
        $payload,
        ['Authorization' => 'Bearer '.$plain, 'Accept' => 'application/json'],
    );
}

test('company MCP tools/list requires a valid bearer token', function () {
    $company = mcpCompany();

    $this->postJson('http://'.$company->subdomain.'.clicflow.fr/mcp', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
    ])->assertStatus(401);
});

test('company MCP initialize and tools/list work with a read token', function () {
    $company = mcpCompany();
    $issued = McpToken::issue(McpToken::AUDIENCE_COMPANY, $company->id, 'Cursor', ['read']);
    $plain = $issued['plain'];

    $init = mcpPost($company, $plain, [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'initialize',
        'params' => ['protocolVersion' => '2025-03-26', 'capabilities' => [], 'clientInfo' => ['name' => 'test', 'version' => '1']],
    ]);
    $init->assertOk()->assertJsonPath('result.protocolVersion', '2025-03-26')
        ->assertJsonPath('result.serverInfo.name', 'Atelier MCP - ClicFlow')->assertJsonPath('result.serverInfo.title', 'Atelier MCP - ClicFlow');

    $list = mcpPost($company, $plain, ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list']);
    $list->assertOk();
    $names = collect($list->json('result.tools'))->pluck('name');
    expect($names)->toContain('list_clients', 'create_client', 'list_prospects', 'create_task', 'update_task_status', 'create_invoice', 'campaigns_stats');
});

test('company MCP is strictly scoped to the token workspace', function () {
    $a = mcpCompany();
    $b = mcpCompany();
    Client::factory()->create(['company_id' => $a->id, 'name' => 'Client A Only', 'email' => 'a@example.test']);
    Client::factory()->create(['company_id' => $b->id, 'name' => 'Client B Secret', 'email' => 'b@example.test']);

    $issued = McpToken::issue(McpToken::AUDIENCE_COMPANY, $a->id, 'read', ['read']);

    $res = mcpPost($a, $issued['plain'], [
        'jsonrpc' => '2.0',
        'id' => 3,
        'method' => 'tools/call',
        'params' => ['name' => 'list_clients', 'arguments' => ['per_page' => 50]],
    ]);
    $res->assertOk();
    $text = $res->json('result.content.0.text');
    expect($text)->toContain('Client A Only');
    expect($text)->not->toContain('Client B Secret');

    // Token A used on company B host is forbidden
    mcpPost($b, $issued['plain'], [
        'jsonrpc' => '2.0',
        'id' => 4,
        'method' => 'tools/list',
    ])->assertStatus(403);
});

test('write tools require the write scope and create a client in the workspace', function () {
    $company = mcpCompany();
    $read = McpToken::issue(McpToken::AUDIENCE_COMPANY, $company->id, 'read-only', ['read']);

    mcpPost($company, $read['plain'], [
        'jsonrpc' => '2.0',
        'id' => 5,
        'method' => 'tools/call',
        'params' => ['name' => 'create_client', 'arguments' => ['name' => 'New Co', 'email' => 'new@co.test']],
    ])->assertOk()->assertJsonPath('error.code', -32003);

    $write = McpToken::issue(McpToken::AUDIENCE_COMPANY, $company->id, 'rw', ['read', 'write']);
    $created = mcpPost($company, $write['plain'], [
        'jsonrpc' => '2.0',
        'id' => 6,
        'method' => 'tools/call',
        'params' => ['name' => 'create_client', 'arguments' => ['name' => 'New Co', 'email' => 'new@co.test']],
    ]);
    $created->assertOk();
    $payload = json_decode($created->json('result.content.0.text'), true);
    expect($payload['created'])->toBeTrue();
    expect(Client::query()->withoutGlobalScopes()->where('company_id', $company->id)->where('email', 'new@co.test')->exists())->toBeTrue();
});

test('a revoked company token is rejected', function () {
    $company = mcpCompany();
    $issued = McpToken::issue(McpToken::AUDIENCE_COMPANY, $company->id, 'temp', ['read']);
    $issued['token']->revoke();

    mcpPost($company, $issued['plain'], [
        'jsonrpc' => '2.0',
        'id' => 7,
        'method' => 'tools/list',
    ])->assertStatus(401);
});

test('admin MCP lists companies with an admin token', function () {
    $admin = actingAsAdmin();
    auth()->guard('admin')->logout();
    $this->app['auth']->forgetGuards();

    $company = mcpCompany(['name' => 'Visible Admin Co']);
    $issued = McpToken::issue(McpToken::AUDIENCE_ADMIN, $admin->id, 'ops', ['read']);

    $res = $this->postJson('/admin/mcp', [
        'jsonrpc' => '2.0',
        'id' => 8,
        'method' => 'tools/call',
        'params' => ['name' => 'list_companies', 'arguments' => ['q' => 'Visible Admin Co']],
    ], ['Authorization' => 'Bearer '.$issued['plain'], 'Accept' => 'application/json']);

    $res->assertOk();
    $text = $res->json('result.content.0.text');
    expect($text)->toContain('Visible Admin Co');
});

test('company settings page shows the MCP endpoint and can create then revoke a token', function () {
    $company = mcpCompany();
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole('company_admin');

    $this->actingAs($user)
        ->get(route('settings.mcp'))
        ->assertOk()
        ->assertSee('mcp', false)
        ->assertSee(__('mcp_settings_title'));

    $store = $this->actingAs($user)->post(route('settings.mcp.store'), [
        'name' => 'Claude',
        'scopes' => ['read', 'write'],
    ]);
    $store->assertRedirect(route('settings.mcp'));
    $store->assertSessionHas('cf_mcp_token_plain');

    $token = McpToken::query()->where('tokenable_id', $company->id)->whereNull('revoked_at')->first();
    expect($token)->not->toBeNull();

    $this->actingAs($user)->delete(route('settings.mcp.destroy', $token))->assertRedirect(route('settings.mcp'));
    expect($token->fresh()->revoked_at)->not->toBeNull();
});
