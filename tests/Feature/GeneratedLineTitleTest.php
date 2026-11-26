<?php

use App\Models\Client;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Invoice;
use App\Models\User;
use App\Services\NovaVoiceCreateDocumentWorkflow;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function genTitleSetup(): array
{
    $company = Company::factory()->create(['default_currency' => 'EUR']);
    CompanySetting::query()->withoutGlobalScopes()->updateOrCreate(['company_id' => $company->id], ['billing' => ['vat_percent' => 20]]);
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->syncRoles(['company_admin']);
    $client = Client::query()->withoutGlobalScopes()->create(['company_id' => $company->id, 'name' => 'Client Gen', 'email' => 'gen@example.test']);

    return [$company->refresh(), $user, $client];
}

test('generated line labels split into a bold title and a small description', function () {
    expect(flowdesk_split_line_label('Création site', 'Maquette + intégration'))->toBe(['title' => 'Création site', 'description' => 'Maquette + intégration'])
        ->and(flowdesk_split_line_label(null, 'Site vitrine — Design sur mesure, 5 pages'))->toBe(['title' => 'Site vitrine', 'description' => 'Design sur mesure, 5 pages'])
        ->and(flowdesk_split_line_label(null, "Audit SEO\nAnalyse technique\nRapport"))->toBe(['title' => 'Audit SEO', 'description' => "Analyse technique\nRapport"])
        ->and(flowdesk_split_line_label(null, 'Formation WordPress'))->toBe(['title' => 'Formation WordPress', 'description' => ''])
        ->and(flowdesk_split_line_label('Hébergement', 'Hébergement'))->toBe(['title' => 'Hébergement', 'description' => '']);

    // Long single-line text without separator stays description-only (legacy rendering).
    $long = str_repeat('Prestation de conseil détaillée ', 5);
    expect(flowdesk_split_line_label(null, $long))->toBe(['title' => null, 'description' => trim($long)])
        ->and(flowdesk_split_line_label(null, ''))->toBe(['title' => null, 'description' => '']);
});

test('Nova voice invoice and quote creation store a title and the detail as description', function () {
    [$company, , $client] = genTitleSetup();
    $wf = app(NovaVoiceCreateDocumentWorkflow::class);
    $items = [
        ['description' => 'Site vitrine — Design sur mesure', 'quantity' => 1, 'unit_amount' => 100000],
        ['title' => 'Maintenance', 'description' => 'Mises à jour mensuelles', 'quantity' => 12, 'unit_amount' => 5000],
        ['description' => str_repeat('Texte long sans séparateur ', 6), 'quantity' => 1, 'unit_amount' => 1000],
    ];
    $call = function (string $method) use ($wf, $company, $client, $items) {
        $m = new ReflectionMethod($wf, $method);
        $m->setAccessible(true);

        return $m->invoke($wf, (string) $company->id, $client, 'EUR', $items, 'Brief');
    };

    $inv = $call('createInvoice')->load('items');
    expect($inv->items->pluck('title')->all())->toBe(['Site vitrine', 'Maintenance', null])
        ->and($inv->items->pluck('description')->all()[0])->toBe('Design sur mesure')
        ->and((int) $inv->subtotal_amount)->toBe(161000);

    $q = $call('createProposal')->load('items');
    expect($q->items->pluck('title')->all())->toBe(['Site vitrine', 'Maintenance', null])
        ->and($q->items[1]->description)->toBe('Mises à jour mensuelles');
});

test('assistant quote prefill lines reach the quote editor with a title', function () {
    [, $user, $client] = genTitleSetup();
    $this->actingAs($user)->withSession(['assistant_proposal_prefill' => [
        'client_id' => $client->id,
        'outline' => 'Refonte du site vitrine et formation',
        'quote_name' => 'Refonte',
        'items' => [
            ['title' => 'Refonte site', 'description' => '', 'quantity' => 1, 'unit_major' => 1200],
            ['description' => 'Formation — 1 journée sur site', 'quantity' => 1, 'unit_major' => 450],
        ],
    ]])->get(route('proposals.create', ['from_assistant' => 1]))->assertOk()
        ->assertSee('Refonte site')
        ->assertSee('1 journée sur site')
        ->assertSee('Formation');
});

test('phone gate is outside the hidden wrapper for staff and skipped for portal clients', function () {
    [$company, $user, $client] = genTitleSetup();
    $html = $this->actingAs($user)->get(route('invoices.create'))->assertOk()->getContent();
    $gate = strpos($html, 'id="fd-mobile-desktop-gate"');
    $wrapper = strpos($html, '<div class="relative z-10">');
    expect($gate)->not->toBeFalse()->and($wrapper)->not->toBeFalse()->and($gate)->toBeLessThan($wrapper);

    $portal = User::factory()->create(['company_id' => $company->id]);
    $portal->syncRoles(['client']);
    $client->forceFill(['user_id' => $portal->id])->save();
    $this->actingAs($portal)->get(route('portal.dashboard'))->assertOk()->assertDontSee('id="fd-mobile-desktop-gate"', false);
});

test('document line tables carry mobile labels for the stacked card view', function () {
    [$company, $user, $client] = genTitleSetup();
    $this->actingAs($user)->post(route('invoices.store'), [
        'client_mode' => 'pick', 'client_id' => $client->id, 'currency' => 'EUR', 'status' => 'draft',
        'items' => [['title' => 'Audit', 'description' => "Analyse\nRapport", 'quantity' => 1, 'unit_amount' => 10000]],
    ])->assertSessionHasNoErrors();
    $inv = Invoice::query()->withoutGlobalScopes()->where('company_id', $company->id)->firstOrFail();
    $this->actingAs($user)->get(route('invoices.show', $inv))->assertOk()
        ->assertSee('cf-doc-lines-table', false)
        ->assertSee('data-label="'.__('Qty').'"', false);
});
