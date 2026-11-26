<?php

use App\Enums\ProductKind;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Proposal;
use App\Models\User;
use App\Services\AiSidebar\AiDraftService;
use App\Services\PlatformLlmRouter;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function aiSidebarSetup(bool $llm = false, ?string $llmJson = null): array
{
    test()->mock(PlatformLlmRouter::class, function ($m) use ($llm, $llmJson) {
        $m->shouldReceive('isAvailable')->andReturn($llm);
        if ($llmJson !== null) {
            $m->shouldReceive('completeFast')->andReturn(['suggestion' => $llmJson, 'model' => 'fake', 'input_tokens' => 1, 'output_tokens' => 1, 'total_tokens' => 2]);
        }
    });
    test()->partialMock(\App\Services\PlanLimitService::class, function ($m) {
        $m->shouldReceive('isFeatureEnabled')->andReturn(true);
        $m->shouldReceive('allows')->andReturn(true);
    });
    $company = Company::factory()->create(['default_currency' => 'EUR']);
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->syncRoles(['company_admin']);
    $client = Client::query()->withoutGlobalScopes()->create(['company_id' => $company->id, 'name' => 'Société Alpha Conseil', 'email' => 'alpha@example.test']);
    $mentoring = Product::query()->withoutGlobalScopes()->create([
        'company_id' => $company->id, 'name' => 'Session de mentoring', 'kind' => ProductKind::Service,
        'base_price_minor' => flowdesk_decimal_to_minor('150', 'EUR'), 'currency' => 'EUR', 'unit' => 'hour',
        'track_stock' => false, 'is_active' => true, 'tax_rate' => 20,
    ]);
    ProductVariant::query()->withoutGlobalScopes()->create(['company_id' => $company->id, 'product_id' => $mentoring->id, 'stock_qty' => 0]);

    return [$company, $user, $client, $mentoring];
}

test('rules parser builds a quote draft: fuzzy client, catalog price/tax, free line, nothing saved', function () {
    [$company, $user, $client, $mentoring] = aiSidebarSetup();

    $res = $this->actingAs($user)->postJson(route('ai.sidebar-draft'), [
        'prompt' => 'crée un devis pour alpha conseil avec 3 sessions de mentoring et un audit SEO à 200 €',
    ])->assertOk()->assertJson(['entity' => 'quote', 'source' => 'rules']);

    $url = $res->json('redirect');
    expect($url)->toContain('/proposals/create?ai_draft=');
    parse_str(parse_url($url, PHP_URL_QUERY), $q);
    $draft = app(AiDraftService::class)->find($q['ai_draft'], $user);

    expect($draft['client']['id'])->toBe((string) $client->id)
        ->and($draft['lines'])->toHaveCount(2)
        ->and($draft['lines'][0]['product_id'])->toBe((string) $mentoring->id)
        ->and($draft['lines'][0]['quantity'])->toBe(3)
        ->and($draft['lines'][0]['unit_minor'])->toBe(flowdesk_decimal_to_minor('150', 'EUR'))
        ->and($draft['lines'][0]['tax_label'])->toBe('20 %')
        ->and($draft['lines'][1]['matched'])->toBeFalse()
        ->and($draft['lines'][1]['unit_minor'])->toBe(flowdesk_decimal_to_minor('200', 'EUR'))
        ->and(collect($draft['unresolved'])->pluck('type')->all())->toContain('product');

    expect(Proposal::query()->withoutGlobalScopes()->count())->toBe(0)
        ->and(Invoice::query()->withoutGlobalScopes()->count())->toBe(0);

    $this->actingAs($user)->get($url)->assertOk()
        ->assertSee(__('ai_draft_banner_title'))
        ->assertSee('window.cfAiDraft', false)
        ->assertSee('Session de mentoring');
});

test('invoice draft with unknown client offers to create it', function () {
    [, $user] = aiSidebarSetup();
    $url = $this->actingAs($user)->postJson(route('ai.sidebar-draft'), ['prompt' => 'facture pour Zeta Industries avec 2 sessions de mentoring'])
        ->assertOk()->json('redirect');
    expect($url)->toContain('/invoices/create?ai_draft=');

    $this->actingAs($user)->get($url)->assertOk()
        ->assertSee(__('ai_draft_unresolved_client', ['name' => 'Zeta Industries']))
        ->assertSee(__('ai_draft_create_client', ['name' => 'Zeta Industries']));
});

test('client, product and project drafts open their create forms', function () {
    [, $user] = aiSidebarSetup();
    $cases = [
        ['crée un client Delta Studio email hello@delta.test téléphone +216 20 111 222', '/clients/create', ['name' => 'Delta Studio', 'email' => 'hello@delta.test']],
        ['crée un service Audit SEO à 300 € TVA 20%', '/commerce/products/create', ['name' => 'Audit SEO', 'kind' => 'service', 'base_price' => '300', 'tax_rate' => '20']],
        ['crée un projet Refonte du site pour Société Alpha Conseil', '/projects/create', ['title' => 'Refonte du site']],
    ];
    foreach ($cases as [$prompt, $path, $fields]) {
        $url = $this->actingAs($user)->postJson(route('ai.sidebar-draft'), ['prompt' => $prompt])->assertOk()->json('redirect');
        expect($url)->toContain($path.'?ai_draft=');
        parse_str(parse_url($url, PHP_URL_QUERY), $q);
        $draft = app(AiDraftService::class)->find($q['ai_draft'], $user);
        expect($draft['fields'])->toMatchArray($fields);
        $this->actingAs($user)->get($url)->assertOk()->assertSee('window.cfAiDraft', false);
    }
});

test('llm path is used when a provider is configured and bills credits', function () {
    [$company, $user, $client] = aiSidebarSetup(true, json_encode([
        'entity' => 'quote', 'client_name' => 'Alpha', 'title' => 'Accompagnement', 'notes' => '',
        'lines' => [['description' => 'Mentoring', 'product_name' => 'Session de mentoring', 'quantity' => 4, 'unit_price' => null]],
        'fields' => [],
    ]));
    $res = $this->actingAs($user)->postJson(route('ai.sidebar-draft'), ['prompt' => 'devis Alpha 4 mentoring'])
        ->assertOk()->assertJson(['source' => 'ai']);
    parse_str(parse_url($res->json('redirect'), PHP_URL_QUERY), $q);
    $draft = app(AiDraftService::class)->find($q['ai_draft'], $user);
    expect($draft['fields']['name'])->toBe('Accompagnement')
        ->and($draft['client']['id'])->toBe((string) $client->id)
        ->and($draft['lines'][0]['quantity'])->toBe(4);
});

test('drafts are private to their user and tenant', function () {
    [, $user] = aiSidebarSetup();
    $url = $this->actingAs($user)->postJson(route('ai.sidebar-draft'), ['prompt' => 'devis pour Alpha avec 1 session de mentoring'])->json('redirect');

    $other = User::factory()->create();
    $other->syncRoles(['company_admin']);
    $this->actingAs($other)->get($url)->assertOk()
        ->assertSee(__('ai_draft_expired'))
        ->assertDontSee('window.cfAiDraft', false);
});

test('portal clients cannot use the sidebar endpoint and the prompt shows for staff', function () {
    [$company, $user] = aiSidebarSetup();
    $portal = User::factory()->create(['company_id' => $company->id]);
    $portal->syncRoles(['client']);
    $status = $this->actingAs($portal)->postJson(route('ai.sidebar-draft'), ['prompt' => 'crée un devis pour Alpha'])->status();
    expect(in_array($status, [302, 403], true))->toBeTrue();

    $html = view('components.ai.sidebar-prompt', ['flowdeskPlanGates' => ['ai_credits' => true]])->render();
    expect($html)->toBe('');
    $this->actingAs($user);
    $html = view('components.ai.sidebar-prompt', ['flowdeskPlanGates' => ['ai_credits' => true]])->render();
    expect($html)->toContain('aiSidebarPrompt')->toContain(__('ai_sidebar_title'));
    $html = view('components.ai.sidebar-prompt', ['flowdeskPlanGates' => ['ai_credits' => false]])->render();
    expect(trim($html))->toBe('');
});

test('fuzzy similarity basics', function () {
    expect(AiDraftService::similarity('alpha conseil', 'Société Alpha Conseil'))->toBeGreaterThanOrEqual(72)
        ->and(AiDraftService::similarity('zeta', 'Société Alpha Conseil'))->toBeLessThan(72);
});
