<?php

use App\Enums\ProductKind;
use App\Models\AiConversation;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Proposal;
use App\Models\User;
use App\Services\NovaAssistantService;
use App\Services\PlatformLlmRouter;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function aiChatSetup(bool $llm = false, ?string $llmReply = null): array
{
    test()->mock(PlatformLlmRouter::class, function ($m) use ($llm, $llmReply) {
        $m->shouldReceive('isAvailable')->andReturn($llm);
        if ($llmReply !== null) {
            $m->shouldReceive('completeFast')->andReturn(['suggestion' => $llmReply, 'model' => 'fake', 'input_tokens' => 1, 'output_tokens' => 1, 'total_tokens' => 2]);
        }
    });
    test()->partialMock(\App\Services\PlanLimitService::class, function ($m) {
        $m->shouldReceive('isFeatureEnabled')->andReturn(true);
        $m->shouldReceive('allows')->andReturn(true);
    });
    $company = Company::factory()->create(['default_currency' => 'EUR']);
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->syncRoles(['company_admin']);
    Client::query()->withoutGlobalScopes()->create(['company_id' => $company->id, 'name' => 'Société Alpha Conseil', 'email' => 'alpha@example.test']);
    foreach ([['Session de mentoring', '150'], ['Pack réseaux sociaux', '450']] as [$name, $price]) {
        $p = Product::query()->withoutGlobalScopes()->create([
            'company_id' => $company->id, 'name' => $name, 'kind' => ProductKind::Service,
            'base_price_minor' => flowdesk_decimal_to_minor($price, 'EUR'), 'currency' => 'EUR', 'unit' => 'hour',
            'track_stock' => false, 'is_active' => true, 'tax_rate' => 20,
        ]);
        ProductVariant::query()->withoutGlobalScopes()->create(['company_id' => $company->id, 'product_id' => $p->id, 'stock_qty' => 0]);
    }

    return [$company, $user];
}

function aiChatLines(): array
{
    return [
        ['description' => 'Session de mentoring', 'quantity' => 3, 'unit_minor' => 15000, 'product_id' => 'x1'],
        ['description' => 'Audit marketing digital', 'quantity' => 1, 'unit_minor' => 39000, 'product_id' => ''],
    ];
}

test('create request prepares a draft, stores the chat and saves nothing', function () {
    [$company, $user] = aiChatSetup();

    $this->actingAs($user)->getJson(route('ai.chat.show'))->assertOk()->assertJson(['messages' => []]);

    $res = $this->actingAs($user)->postJson(route('ai.chat.send'), [
        'message' => 'crée un devis pour alpha conseil avec 3 sessions de mentoring et un audit à 390 €',
    ])->assertOk();

    expect($res->json('action.type'))->toBe('redirect')
        ->and($res->json('action.url'))->toContain('/proposals/create?ai_draft=')
        ->and($res->json('assistant.meta.type'))->toBe('draft')
        ->and($res->json('assistant.content'))->toContain(__('ai_chat_entity_quote'))->toContain('Société Alpha Conseil')->toContain('840')->toContain('EUR')
        ->and(Proposal::query()->withoutGlobalScopes()->count())->toBe(0)
        ->and(Invoice::query()->withoutGlobalScopes()->count())->toBe(0);

    $conv = AiConversation::query()->where('user_id', $user->id)->first();
    expect($conv->channel)->toBe('sidebar');

    $history = $this->actingAs($user)->getJson(route('ai.chat.show'))->assertOk();
    expect($history->json('messages'))->toHaveCount(2)
        ->and($history->json('messages.0.role'))->toBe('user')
        ->and($history->json('messages.1.meta.url'))->toBe($res->json('action.url'))
        ->and($history->json('conversation_id'))->toBe((string) $conv->id);

    // Follow-up in the same conversation keeps the history.
    $this->actingAs($user)->postJson(route('ai.chat.send'), ['message' => 'merci', 'conversation_id' => $conv->id])->assertOk();
    expect($conv->messages()->count())->toBe(4);
});

test('follow-ups adjust the open quote form via ops (rules)', function () {
    [$company, $user] = aiChatSetup();
    $ctx = ['form' => 'quote', 'currency' => 'EUR', 'lines' => aiChatLines()];

    $r = $this->actingAs($user)->postJson(route('ai.chat.send'), ['message' => 'ajoute 2 sessions', 'context' => $ctx])->assertOk();
    expect($r->json('action.type'))->toBe('ops')
        ->and($r->json('action.ops'))->toBe([['op' => 'quantity', 'index' => 0, 'quantity' => 5]]);

    $r = $this->actingAs($user)->postJson(route('ai.chat.send'), ['message' => 'mets 10% de remise', 'context' => $ctx])->assertOk();
    expect($r->json('action.ops.0.op'))->toBe('discount')->and($r->json('action.ops.0.type'))->toBe('percent')
        ->and((float) $r->json('action.ops.0.value'))->toBe(10.0)->and($r->json('action.ops.0.index'))->toBeNull();

    $r = $this->actingAs($user)->postJson(route('ai.chat.send'), ['message' => 'fais une remise de 50 €', 'context' => $ctx])->assertOk();
    expect($r->json('action.ops.0.type'))->toBe('amount')->and((float) $r->json('action.ops.0.value'))->toBe(50.0);

    $r = $this->actingAs($user)->postJson(route('ai.chat.send'), ['message' => "retire l'audit", 'context' => $ctx])->assertOk();
    expect($r->json('action.ops'))->toBe([['op' => 'remove', 'index' => 1]]);

    $r = $this->actingAs($user)->postJson(route('ai.chat.send'), ['message' => 'ajoute 1 pack réseaux sociaux', 'context' => $ctx])->assertOk();
    expect($r->json('action.ops.0.op'))->toBe('add')
        ->and($r->json('action.ops.0.line.description'))->toBe('Pack réseaux sociaux')
        ->and($r->json('action.ops.0.line.unit_minor'))->toBe(45000);

    $r = $this->actingAs($user)->postJson(route('ai.chat.send'), ['message' => 'passe la session de mentoring à 180 €', 'context' => $ctx])->assertOk();
    expect($r->json('action.ops.0.op'))->toBe('price')->and((float) $r->json('action.ops.0.unit_major'))->toBe(180.0);

    expect(Proposal::query()->withoutGlobalScopes()->count())->toBe(0);
});

test('follow-up adjust uses LLM ops when a provider is available', function () {
    [$company, $user] = aiChatSetup(true, '{"ops":[{"op":"quantity","line":0,"quantity":7}]}');
    $r = $this->actingAs($user)->postJson(route('ai.chat.send'), [
        'message' => 'mets la quantité des sessions à 7',
        'context' => ['form' => 'quote', 'currency' => 'EUR', 'lines' => aiChatLines()],
    ])->assertOk();
    expect($r->json('action.ops'))->toBe([['op' => 'quantity', 'index' => 0, 'quantity' => 7]])
        ->and($r->json('assistant.meta.source'))->toBe('ai');
});

test('general question without AI key gets the help answer; with AI it gets the model reply', function () {
    [$company, $user] = aiChatSetup();
    $r = $this->actingAs($user)->postJson(route('ai.chat.send'), ['message' => 'quel est mon chiffre ?'])->assertOk();
    expect($r->json('assistant.content'))->toBe(__('ai_chat_no_llm'))->and($r->json('action'))->toBeNull();
});

test('general question with AI uses the model reply', function () {
    [$company, $user] = aiChatSetup(true, 'Votre CA du mois est de 0 €.');
    $r = $this->actingAs($user)->postJson(route('ai.chat.send'), ['message' => 'quel est mon chiffre ?'])->assertOk();
    expect($r->json('assistant.content'))->toBe('Votre CA du mois est de 0 €.');
});

test('conversations are isolated per user and tenant; new conversation resets the history', function () {
    [$company, $user] = aiChatSetup();
    $other = User::factory()->create(['company_id' => Company::factory()->create()->id]);
    $other->syncRoles(['company_admin']);

    $first = $this->actingAs($user)->postJson(route('ai.chat.send'), ['message' => 'bonjour'])->assertOk();
    $convId = $first->json('conversation_id');

    // Another tenant cannot write into (or read) this conversation.
    $res = $this->actingAs($other)->postJson(route('ai.chat.send'), ['message' => 'salut', 'conversation_id' => $convId])->assertOk();
    expect($res->json('conversation_id'))->not->toBe($convId);
    expect($this->actingAs($other)->getJson(route('ai.chat.show'))->json('messages'))->toHaveCount(2);
    expect(AiConversation::find($convId)->messages()->count())->toBe(2);

    $new = $this->actingAs($user)->postJson(route('ai.chat.new'))->assertOk();
    expect($new->json('conversation_id'))->not->toBe($convId);
    expect($this->actingAs($user)->getJson(route('ai.chat.show'))->json('messages'))->toBe([]);

    // Nova chat history lists do not include panel conversations.
    expect(app(NovaAssistantService::class)->recentConversations($company, $user))->toHaveCount(0);
});

test('portal client cannot use the chat panel', function () {
    [$company, $user] = aiChatSetup();
    $client = User::factory()->create(['company_id' => $company->id]);
    $client->syncRoles(['client']);
    $status = $this->actingAs($client)->postJson(route('ai.chat.send'), ['message' => 'crée un devis pour alpha'])->status();
    expect(in_array($status, [302, 403], true))->toBeTrue();
});

test('layout renders the panel and launchers for staff', function () {
    [$company, $user] = aiChatSetup();
    $this->partialMock(\App\Services\PlanLimitService::class, function ($m) {
        $m->shouldReceive('isFeatureEnabled')->andReturn(true);
        $m->shouldReceive('allows')->andReturn(true);
        $m->shouldReceive('featureGates')->andReturn(['ai_credits' => true]);
    });
    $html = $this->actingAs($user)->get(route('dashboard'))->assertOk()->getContent();
    expect($html)->toContain('data-ai-chat-panel')->toContain('data-ai-chat-launcher')->not->toContain('data-ai-sidebar-prompt');
    expect(substr_count($html, 'data-ai-chat-launcher'))->toBe(1);
});

test('date parser understands French relative dates', function () {
    $p = new \App\Services\AiSidebar\AiDateParser;
    $today = \Carbon\Carbon::parse('2026-11-18'); // Wednesday
    expect($p->parse('pour vendredi', $today))->toBe('2026-11-20')
        ->and($p->parse('lundi prochain', $today))->toBe('2026-11-23')
        ->and($p->parse('mercredi', $today))->toBe('2026-11-25')
        ->and($p->parse('demain', $today))->toBe('2026-11-19')
        ->and($p->parse('après-demain', $today))->toBe('2026-11-20')
        ->and($p->parse('dans 3 jours', $today))->toBe('2026-11-21')
        ->and($p->parse('dans deux semaines', $today))->toBe('2026-12-02')
        ->and($p->parse('fin du mois', $today))->toBe('2026-11-30')
        ->and($p->parse('semaine prochaine', $today))->toBe('2026-11-23')
        ->and($p->parse('le 30 décembre', $today))->toBe('2026-12-30')
        ->and($p->parse('le 2 janvier', $today))->toBe('2027-01-02')
        ->and($p->parse('15/01/2027', $today))->toBe('2027-01-15')
        ->and($p->parse('rien à voir', $today))->toBeNull();
});

test('task request resolves project, assignee and due date, then opens the project tasks tab', function () {
    [$company, $user] = aiChatSetup();
    $sami = User::factory()->create(['company_id' => $company->id, 'name' => 'Sami Trabelsi']);
    $sami->syncRoles(['team_member']);
    $project = \App\Models\Project::query()->withoutGlobalScopes()->create(['company_id' => $company->id, 'title' => 'Site vitrine Alpha', 'status' => 'in_progress']);
    \Carbon\Carbon::setTestNow('2026-11-18 10:00:00');

    $r = $this->actingAs($user)->postJson(route('ai.chat.send'), [
        'message' => "crée une tâche pour le projet site vitrine alpha : maquette page d'accueil, pour vendredi, assignée à Sami, urgente",
    ])->assertOk();

    expect($r->json('action.type'))->toBe('redirect')
        ->and($r->json('action.url'))->toContain('/projects/'.$project->id)->toContain('tab=tasks')->toContain('ai_draft=')
        ->and($r->json('assistant.meta.entity'))->toBe('task')
        ->and(\App\Models\ProjectTask::query()->withoutGlobalScopes()->count())->toBe(0);

    parse_str((string) parse_url($r->json('action.url'), PHP_URL_QUERY), $q);
    $draft = app(\App\Services\AiSidebar\AiDraftService::class)->find($q['ai_draft'], $user);
    expect($draft['fields'])->toMatchArray(['title' => "Maquette page d'accueil", 'ends_on' => '2026-11-20', 'priority' => 'urgent'])
        ->and($draft['assignees'][0]['id'])->toBe((string) $sami->id)
        ->and($draft['form_selector'])->toBe('form[action$="/tasks"]');

    // The project page renders the draft banner + payload only for that project.
    $this->actingAs($user)->get($r->json('action.url'))->assertOk()->assertSee('data-ai-draft-banner', false);

    // Unknown project: no redirect, asks which project.
    $r2 = $this->actingAs($user)->postJson(route('ai.chat.send'), ['message' => 'crée une tâche pour le projet Inconnu XYZ : test'])->assertOk();
    expect($r2->json('action'))->toBeNull()->and($r2->json('assistant.content'))->toContain('Inconnu XYZ');
    \Carbon\Carbon::setTestNow();
});

test('open client / product / project forms get live field ops (rules, nothing saved)', function () {
    [$company, $user] = aiChatSetup();

    $r = $this->actingAs($user)->postJson(route('ai.chat.send'), ['message' => 'mets la catégorie VIP', 'context' => ['form' => 'client', 'fields' => ['name' => 'Beta']]])->assertOk();
    expect($r->json('action.type'))->toBe('ops')->and($r->json('action.ops'))->toBe([['op' => 'field', 'name' => 'category', 'value' => 'VIP']]);

    $r = $this->actingAs($user)->postJson(route('ai.chat.send'), ['message' => 'change le prix à 200 € et TVA 20 %', 'context' => ['form' => 'product', 'fields' => []]])->assertOk();
    expect($r->json('action.ops'))->toBe([
        ['op' => 'field', 'name' => 'base_price', 'value' => '200'],
        ['op' => 'field', 'name' => 'tax_rate', 'value' => '20'],
    ]);

    $r = $this->actingAs($user)->postJson(route('ai.chat.send'), ['message' => 'ajoute le téléphone +216 22 333 444', 'context' => ['form' => 'client', 'fields' => []]])->assertOk();
    expect($r->json('action.ops.0'))->toBe(['op' => 'field', 'name' => 'phone', 'value' => '+216 22 333 444']);

    \Carbon\Carbon::setTestNow('2026-11-18 10:00:00');
    $r = $this->actingAs($user)->postJson(route('ai.chat.send'), ['message' => 'date de fin au 30 décembre', 'context' => ['form' => 'project', 'fields' => []]])->assertOk();
    expect($r->json('action.ops.0'))->toBe(['op' => 'field', 'name' => 'final_deadline', 'value' => '2026-12-30']);
    \Carbon\Carbon::setTestNow();

    $r = $this->actingAs($user)->postJson(route('ai.chat.send'), ['message' => 'passe le statut en prospect', 'context' => ['form' => 'client', 'fields' => []]])->assertOk();
    expect($r->json('action.ops.0'))->toBe(['op' => 'field', 'name' => 'status', 'value' => 'potential']);

    expect(Client::query()->withoutGlobalScopes()->where('category', 'VIP')->count())->toBe(0);
});

test('field ops via LLM are whitelisted and normalized', function () {
    [$company, $user] = aiChatSetup(true, json_encode(['fields' => [
        ['field' => 'category', 'value' => 'Grand compte'],
        ['field' => 'password', 'value' => 'hack'],
        ['field' => 'email', 'value' => 'pas-un-email'],
    ]]));

    $r = $this->actingAs($user)->postJson(route('ai.chat.send'), ['message' => 'mets la catégorie grand compte', 'context' => ['form' => 'client', 'fields' => []]])->assertOk();
    expect($r->json('action.ops'))->toBe([['op' => 'field', 'name' => 'category', 'value' => 'Grand compte']])
        ->and($r->json('assistant.meta.source'))->toBe('ai');
});

test('chat voice is independent of the company Nova switch; only the admin switch hides it', function () {
    [$company, $user] = aiChatSetup();
    $this->partialMock(\App\Services\PlanLimitService::class, function ($m) {
        $m->shouldReceive('isFeatureEnabled')->andReturn(true);
        $m->shouldReceive('allows')->andReturn(true);
        $m->shouldReceive('featureGates')->andReturn(['ai_credits' => true]);
    });

    // Both switches on → chat mic + speaker + auto-send, and the top-bar Nova widget.
    $html = $this->actingAs($user)->get(route('dashboard'))->assertOk()->getContent();
    expect($html)->toContain('data-ai-chat-mic')->toContain('data-ai-chat-speaker')->toContain('data-ai-chat-autosend')
        ->toContain('en-US')->toContain('flow-nova-topbar');

    // Company top-bar Nova off → top-bar widget gone, chat mic still there.
    \App\Models\CompanySetting::query()->withoutGlobalScopes()->updateOrCreate(['company_id' => $company->id], ['nova_voice_enabled' => false]);
    $html = $this->actingAs($user)->get(route('dashboard'))->assertOk()->getContent();
    expect($html)->toContain('data-ai-chat-panel')->toContain('data-ai-chat-mic')->toContain('data-ai-chat-speaker')
        ->not->toContain('flow-nova-topbar');
    // Nova top-bar endpoints stay refused; the chat read-aloud endpoint is allowed (422 = reached validation, no TTS call).
    $this->actingAs($user)->postJson(route('assistant.speak'), [])->assertStatus(403)->assertJsonPath('error', 'nova_voice_disabled');
    $this->actingAs($user)->postJson(route('assistant.voice-intent'), [])->assertStatus(403);
    $this->actingAs($user)->postJson(route('assistant.chat-speak'), [])->assertStatus(422);

    // Platform admin switch off → both hidden, chat read-aloud refused.
    \App\Models\CompanySetting::query()->withoutGlobalScopes()->where('company_id', $company->id)->update(['nova_voice_enabled' => true]);
    \App\Models\Company::query()->withoutGlobalScopes()->whereKey($company->id)->update(['admin_nova_voice_enabled' => false]);
    $html = $this->actingAs($user)->get(route('dashboard'))->assertOk()->getContent();
    expect($html)->toContain('data-ai-chat-panel')->not->toContain('data-ai-chat-mic')->not->toContain('flow-nova-topbar');
    $this->actingAs($user)->postJson(route('assistant.chat-speak'), [])->assertStatus(403);
});
