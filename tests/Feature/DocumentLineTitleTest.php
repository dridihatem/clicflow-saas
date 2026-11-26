<?php

use App\Enums\InvoiceStatus;
use App\Enums\ProposalStatus;
use App\Models\Client;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Proposal;
use App\Models\User;
use App\Services\DataImportService;
use App\Services\InvoicePdfService;
use App\Services\ProposalPdfService;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function lineTitleSetup(): array
{
    $company = Company::factory()->create(['default_currency' => 'EUR']);
    CompanySetting::query()->withoutGlobalScopes()->updateOrCreate(['company_id' => $company->id], ['billing' => ['vat_percent' => 20]]);
    $company->refresh();
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->syncRoles(['company_admin']);
    $client = Client::query()->withoutGlobalScopes()->create(['company_id' => $company->id, 'name' => 'Client Titre', 'email' => 'titre@example.test']);

    return [$company, $user, $client];
}

function lineTitleInvoicePayload(Client $client, array $items, array $extra = []): array
{
    return $extra + ['client_mode' => 'pick', 'client_id' => $client->id, 'currency' => 'EUR', 'status' => InvoiceStatus::Draft->value, 'items' => $items];
}

test('invoice lines store a title + multi-line description; PDF shows bold title and small escaped description', function () {
    [$company, $user, $client] = lineTitleSetup();
    $this->actingAs($user)->post(route('invoices.store'), lineTitleInvoicePayload($client, [
        ['title' => 'Création site web', 'description' => "Maquette + intégration\n5 pages <b>responsive</b>", 'quantity' => 1, 'unit_amount' => 100000],
        ['title' => 'Hébergement', 'description' => '', 'quantity' => 12, 'unit_amount' => 1000],
        ['description' => 'Ligne libre sans titre', 'quantity' => 1, 'unit_amount' => 500],
    ]))->assertSessionHasNoErrors();

    $inv = Invoice::query()->withoutGlobalScopes()->where('company_id', $company->id)->firstOrFail()->load('items');
    expect($inv->items->pluck('title')->all())->toBe(['Création site web', 'Hébergement', null])
        ->and($inv->items[1]->description)->toBe('')
        ->and([(int) $inv->subtotal_amount, (int) $inv->amount])->toBe([112500, 135000]);

    $html = app(InvoicePdfService::class)->html($inv);
    expect($html)->toContain('<span class="line-title" style="display:block;font-weight:bold;">Création site web</span>')
        ->toContain('Maquette + intégration<br />')
        ->toContain('5 pages &lt;b&gt;responsive&lt;/b&gt;')
        ->not->toContain('<b>responsive</b>')
        ->toContain('Ligne libre sans titre');

    $this->actingAs($user)->get(route('invoices.show', $inv))->assertOk()
        ->assertSee('data-line-title', false)->assertSee('Création site web');

    // Duplicate keeps the title.
    $this->actingAs($user)->post(route('invoices.duplicate', $inv))->assertRedirect();
    $copy = Invoice::query()->withoutGlobalScopes()->where('company_id', $company->id)->where('id', '!=', $inv->id)->firstOrFail()->load('items');
    expect($copy->items->pluck('title')->all())->toBe(['Création site web', 'Hébergement', null]);
});

test('a line needs a title or a description; long descriptions fit invoice lines', function () {
    [$company, $user, $client] = lineTitleSetup();
    $this->actingAs($user)->post(route('invoices.store'), lineTitleInvoicePayload($client, [
        ['title' => '', 'description' => '', 'quantity' => 1, 'unit_amount' => 100],
    ]))->assertSessionHasErrors('items.0.description');

    $long = str_repeat("Détail de prestation.\n", 60);
    $this->actingAs($user)->post(route('invoices.store'), lineTitleInvoicePayload($client, [
        ['title' => 'Audit', 'description' => $long, 'quantity' => 1, 'unit_amount' => 100],
    ]))->assertSessionHasNoErrors();
    expect(InvoiceItem::query()->withoutGlobalScopes()->where('company_id', $company->id)->value('description'))->toBe(rtrim($long));
});

test('legacy lines without title render exactly as before', function () {
    [$company, , $client] = lineTitleSetup();
    $inv = Invoice::query()->withoutGlobalScope('tenant')->create([
        'company_id' => $company->id, 'client_id' => $client->id, 'number' => 'F-OLD-1', 'status' => InvoiceStatus::Sent->value,
        'subtotal_amount' => 10000, 'vat_amount' => 2000, 'fiscal_stamp_amount' => 0, 'amount' => 12000, 'currency' => 'EUR',
    ]);
    InvoiceItem::query()->withoutGlobalScopes()->create([
        'company_id' => $company->id, 'invoice_id' => $inv->id, 'description' => 'Ancienne ligne — détail', 'quantity' => 1, 'unit_amount' => 10000, 'total_amount' => 10000,
    ]);
    $inv = $inv->fresh('items');
    $html = app(InvoicePdfService::class)->html($inv);
    expect($html)->toContain('<td class="desc text-start">Ancienne ligne — détail</td>')
        ->not->toContain('line-title')
        ->and([(int) $inv->subtotal_amount, (int) $inv->vat_amount, (int) $inv->amount])->toBe([10000, 2000, 12000]);
});

test('quote lines keep the title through update, PDF, preview and conversion to invoice', function () {
    [$company, $user, $client] = lineTitleSetup();
    $items = [
        ['title' => 'Pack SEO', 'description' => "Audit\nOptimisation", 'quantity' => 1, 'unit_amount' => 50000, 'vat_rate' => '20'],
        ['description' => 'Frais divers', 'quantity' => 1, 'unit_amount' => 1000, 'vat_rate' => '20'],
    ];
    $this->actingAs($user)->post(route('proposals.store'), ['name' => 'Devis titres', 'client_id' => $client->id, 'currency' => 'EUR', 'items' => $items])->assertSessionHasNoErrors();
    $q = Proposal::query()->withoutGlobalScopes()->where('company_id', $company->id)->firstOrFail()->load('items');
    expect($q->items->pluck('title')->all())->toBe(['Pack SEO', null]);
    expect(app(ProposalPdfService::class)->html($q))->toContain('font-weight:bold;">Pack SEO</span>')->toContain('Audit<br />');

    $this->actingAs($user)->get(route('proposals.show', $q))->assertOk()->assertSee('Pack SEO');
    $this->actingAs($user)->get(route('proposals.edit', $q))->assertOk()->assertSee('data-line-title', false)->assertSee('Pack SEO');

    $r = $this->actingAs($user)->put(route('proposals.draft-preview'), ['name' => 'Aperçu', 'client_id' => $client->id, 'currency' => 'EUR', 'items' => $items]);
    $r->assertOk();

    $q->update(['status' => ProposalStatus::Accepted]);
    $this->actingAs($user)->post(route('proposals.invoice', $q))->assertRedirect();
    $inv = Invoice::query()->withoutGlobalScopes()->where('proposal_id', $q->id)->firstOrFail()->load('items');
    expect($inv->items->pluck('title')->all())->toBe(['Pack SEO', null])
        ->and($inv->items[0]->description)->toBe("Audit\nOptimisation")
        ->and((int) $inv->amount)->toBe((int) $q->amount);
});

test('the shared editor shows title input + description textarea and the wider container', function () {
    [, $user] = lineTitleSetup();
    foreach ([route('invoices.create'), route('proposals.create')] as $url) {
        $this->actingAs($user)->get($url)->assertOk()
            ->assertSee('data-line-title', false)
            ->assertSee("'items['+i+'][title]'", false)
            ->assertSee('<textarea data-line-desc', false)
            ->assertSee('max-w-12xl', false)
            ->assertDontSee('mx-auto max-w-7xl', false);
    }
});

test('invoice import maps a "Titre ligne" column to the line title', function () {
    [$company, $user] = lineTitleSetup();
    $csv = "N° facture,Client,Titre ligne,Description,Quantité,Prix unitaire\n"
        ."F-T-1,Client Import,Formation Laravel,2 jours en présentiel,1,100\n";
    $s = app(DataImportService::class);
    $rows = $s->parseDelimited($csv);
    $auto = $s->classifyHeaders('invoices', array_keys($rows[0]))['mapping'];
    $map = array_map(fn ($h) => $auto[$h] ?? '', array_keys($rows[0]));
    expect($map[2])->toBe('line_title')->and($map[3])->toBe('description');

    $this->actingAs($user)->post(route('import.store', 'invoices'), ['raw' => $csv, 'mapping' => $map])->assertRedirect(route('import.index'));
    $item = Invoice::query()->withoutGlobalScopes()->where('company_id', $company->id)->with('items')->firstOrFail()->items->first();
    expect($item->title)->toBe('Formation Laravel')->and($item->description)->toBe('2 jours en présentiel');
});

test('REST API accepts an optional title and stays compatible without it', function () {
    expect(flowdesk_line_title("  Titre \n  long  "))->toBe('Titre long')
        ->and(flowdesk_line_title(''))->toBeNull()
        ->and(flowdesk_line_title(null))->toBeNull();
});
