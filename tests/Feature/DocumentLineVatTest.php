<?php

use App\Enums\InvoiceStatus;
use App\Enums\ProposalStatus;
use App\Models\Client;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\Proposal;
use App\Models\ProposalItem;
use App\Models\User;
use App\Services\AiSidebar\AiChatService;
use App\Services\DataImportService;
use App\Services\InvoicePdfService;
use App\Services\InvoiceTotalsService;
use App\Services\ProposalPdfService;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function lineVatSetup(): array
{
    $company = Company::factory()->create(['default_currency' => 'EUR']);
    CompanySetting::query()->withoutGlobalScopes()->updateOrCreate(['company_id' => $company->id], ['billing' => ['vat_percent' => 20]]);
    $company->refresh();
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->syncRoles(['company_admin']);
    $client = Client::query()->withoutGlobalScopes()->create([
        'company_id' => $company->id, 'name' => 'Client TVA', 'email' => 'tva@example.test',
        'address' => ['line1' => '12 rue des Lilas', 'postal_code' => '75011', 'city' => 'Paris', 'country' => 'FR'],
    ]);

    return [$company, $user, $client];
}

function invoicePayload(Client $client, array $items, array $extra = []): array
{
    return $extra + [
        'client_mode' => 'pick',
        'client_id' => $client->id,
        'currency' => 'EUR',
        'status' => InvoiceStatus::Draft->value,
        'items' => $items,
    ];
}

function legacyInvoice(Company $company, Client $client): Invoice
{
    $inv = Invoice::query()->withoutGlobalScope('tenant')->create([
        'company_id' => $company->id, 'client_id' => $client->id, 'number' => 'F-LEG-1', 'status' => InvoiceStatus::Sent->value,
        'subtotal_amount' => 30000, 'vat_amount' => 6000, 'fiscal_stamp_amount' => 0, 'amount' => 36000, 'currency' => 'EUR',
    ]);
    foreach ([[2, 10000], [1, 10000]] as [$q, $u]) {
        InvoiceItem::query()->withoutGlobalScopes()->create([
            'company_id' => $company->id, 'invoice_id' => $inv->id, 'description' => 'Ligne '.$u, 'quantity' => $q, 'unit_amount' => $u, 'total_amount' => $q * $u,
        ]);
    }

    return $inv->fresh('items');
}

test('regression: legacy documents (no per-line rate) keep their stored totals and a single VAT row', function () {
    [$company, , $client] = lineVatSetup();
    // The workspace rate changed since: the stored document must not move.
    CompanySetting::query()->withoutGlobalScopes()->where('company_id', $company->id)->update(['billing' => ['vat_percent' => 19]]);
    $inv = legacyInvoice($company, $client);

    expect($inv->hasLineVatRates())->toBeFalse()
        ->and($inv->vatBreakdown())->toHaveCount(1)
        ->and($inv->vatBreakdown()[0]['vat'])->toBe(6000)
        ->and($inv->vatBreakdown()[0]['label'])->toBe('20 %')
        ->and(array_sum($inv->items->map(fn ($i) => $inv->lineVatMinor($i))->all()))->toBe(6000)
        ->and([(int) $inv->subtotal_amount, (int) $inv->vat_amount, (int) $inv->amount])->toBe([30000, 6000, 36000]);

    $html = app(InvoicePdfService::class)->html($inv);
    expect($html)->toContain(flowdesk_format_minor(36000, 'EUR'))->toContain('75011 Paris')->toContain('VAT (20%)');
});

test('totals service: one rate for every line equals the legacy single-rate formula', function () {
    [$company] = lineVatSetup();
    $svc = app(InvoiceTotalsService::class);
    foreach ([[12345, 999], [100000, 1], [7, 3]] as [$a, $b]) {
        foreach ([[null, null], ['20', '20'], [20.0, null]] as [$ra, $rb]) {
            foreach ([[null, null], ['percent', '10'], ['amount', '15,5']] as [$t, $v]) {
                $rows = [['total' => $a, 'vat_rate' => $ra], ['total' => $b, 'vat_rate' => $rb]];
                $new = $svc->fromItemsMinor($rows, $company, $t, $v, 'EUR');
                $old = $svc->fromLinesMinor($a + $b, $company, $t, $v, 'EUR');
                expect([$new['subtotal'], $new['vat'], $new['stamp'], $new['total'], $new['discount']])
                    ->toBe([$old['subtotal'], $old['vat'], $old['stamp'], $old['total'], $old['discount']]);
            }
        }
    }
});

test('totals service: mixed rates spread the discount proportionally before VAT', function () {
    [$company] = lineVatSetup();
    $t = app(InvoiceTotalsService::class)->fromItemsMinor([
        ['total' => 10000, 'vat_rate' => 20],
        ['total' => 5000, 'vat_rate' => 0],
        ['total' => 3333, 'vat_rate' => '5,5'],
    ], $company, 'percent', '10', 'EUR');

    expect($t['lines'])->toBe(18333)->and($t['discount'])->toBe(1833)->and($t['subtotal'])->toBe(16500);
    $byRate = collect($t['breakdown'])->keyBy(fn ($r) => (string) $r['rate']);
    expect(array_sum(array_column($t['breakdown'], 'base')))->toBe(16500)
        ->and($t['vat'])->toBe(array_sum(array_column($t['breakdown'], 'vat')))
        ->and((int) $byRate['0']['vat'])->toBe(0)
        ->and($t['total'])->toBe(16500 + $t['vat'])
        ->and(array_column($t['breakdown'], 'rate'))->toBe([20.0, 5.5, 0.0]);
});

test('invoice store / update / duplicate keep per-line VAT and compute a per-rate breakdown', function () {
    [$company, $user, $client] = lineVatSetup();
    $this->actingAs($user)->post(route('invoices.store'), invoicePayload($client, [
        ['description' => 'Formation', 'quantity' => 1, 'unit_amount' => 10000, 'vat_rate' => '20'],
        ['description' => 'Livre', 'quantity' => 1, 'unit_amount' => 5000, 'vat_rate' => '0'],
    ], ['discount_type' => 'percent', 'discount_value' => '10']))->assertSessionHasNoErrors();

    $inv = Invoice::query()->withoutGlobalScopes()->where('company_id', $company->id)->latest()->firstOrFail()->load('items');
    expect([(int) $inv->discount_amount, (int) $inv->subtotal_amount, (int) $inv->vat_amount, (int) $inv->amount])->toBe([1500, 13500, 1800, 15300])
        ->and($inv->items->pluck('vat_rate')->map(fn ($v) => (float) $v)->all())->toBe([20.0, 0.0])
        ->and($inv->hasLineVatRates())->toBeTrue()
        ->and(collect($inv->vatBreakdown())->pluck('vat')->all())->toBe([1800, 0]);

    $html = app(InvoicePdfService::class)->html($inv);
    expect($html)->toContain('VAT %')->toContain('VAT (20%)')->toContain('VAT (0%)');
    $this->actingAs($user)->get(route('invoices.show', $inv))->assertOk()->assertSee('data-vat-row', false);

    // Empty rate → workspace default (identical to the old behaviour).
    $this->actingAs($user)->put(route('invoices.update', $inv), invoicePayload($client, [
        ['description' => 'Formation', 'quantity' => 2, 'unit_amount' => 10000, 'vat_rate' => ''],
    ], ['number' => $inv->number]))->assertSessionHasNoErrors();
    $inv->refresh()->load('items');
    expect([(int) $inv->subtotal_amount, (int) $inv->vat_amount, (int) $inv->amount])->toBe([20000, 4000, 24000])
        ->and((float) $inv->items->first()->vat_rate)->toBe(20.0);

    $this->actingAs($user)->post(route('invoices.duplicate', $inv))->assertRedirect();
    $copy = Invoice::query()->withoutGlobalScopes()->where('company_id', $company->id)->where('id', '!=', $inv->id)->latest()->firstOrFail()->load('items');
    expect([(int) $copy->vat_amount, (int) $copy->amount])->toBe([4000, 24000])->and((float) $copy->items->first()->vat_rate)->toBe(20.0);
});

test('quote store keeps line VAT and converts to an invoice with the same totals', function () {
    [$company, $user, $client] = lineVatSetup();
    $this->actingAs($user)->post(route('proposals.store'), [
        'name' => 'Devis multi TVA', 'client_id' => $client->id, 'currency' => 'EUR',
        'items' => [
            ['description' => 'Site', 'quantity' => 1, 'unit_amount' => 100000, 'vat_rate' => '20'],
            ['description' => 'Hébergement export', 'quantity' => 1, 'unit_amount' => 20000, 'vat_rate' => '0'],
        ],
    ])->assertSessionHasNoErrors();
    $q = Proposal::query()->withoutGlobalScopes()->where('company_id', $company->id)->firstOrFail()->load('items');
    expect([(int) $q->subtotal_amount, (int) $q->vat_amount, (int) $q->amount])->toBe([120000, 20000, 140000]);
    expect(app(ProposalPdfService::class)->html($q))->toContain('VAT %');

    $q->update(['status' => ProposalStatus::Accepted]);
    $this->actingAs($user)->post(route('proposals.invoice', $q))->assertRedirect();
    $inv = Invoice::query()->withoutGlobalScopes()->where('proposal_id', $q->id)->firstOrFail()->load('items');
    expect([(int) $inv->subtotal_amount, (int) $inv->vat_amount, (int) $inv->amount])->toBe([120000, 20000, 140000])
        ->and($inv->items->pluck('vat_rate')->map(fn ($v) => (float) $v)->all())->toBe([20.0, 0.0]);
});

test('quick add product from the editor returns a catalog entry with its own VAT; clients cannot', function () {
    [$company, $user, $client] = lineVatSetup();
    $r = $this->actingAs($user)->postJson(route('commerce.products.store'), [
        'name' => 'Audit SEO', 'kind' => 'service', 'base_price' => '450.50', 'currency' => 'EUR', 'tax_rate' => '10', 'unit' => 'day', 'category' => 'Conseil', 'track_stock' => '0',
    ])->assertCreated()->assertJsonPath('ok', true)->json('product');
    expect($r['name'])->toBe('Audit SEO')->and($r['tax_rate'])->toEqual(10.0)->and($r['price_minor'])->toBe(45050)->and($r['kind'])->toBe('service');
    expect(Product::query()->withoutGlobalScopes()->where('company_id', $company->id)->where('name', 'Audit SEO')->value('category'))->toBe('Conseil');

    $this->actingAs($user)->postJson(route('commerce.products.store'), ['name' => '', 'base_price' => 'x', 'currency' => 'EUR'])->assertStatus(422);

    $portal = User::factory()->create(['company_id' => $company->id]);
    $portal->syncRoles(['client']);
    $status = $this->actingAs($portal)->postJson(route('commerce.products.store'), ['name' => 'X', 'base_price' => '1', 'currency' => 'EUR'])->status();
    expect(in_array($status, [302, 403], true))->toBeTrue()
        ->and(Product::query()->withoutGlobalScopes()->where('company_id', $company->id)->where('name', 'X')->exists())->toBeFalse();
});

test('PDF preview renders the unsaved editor content without storing anything', function () {
    [$company, $user, $client] = lineVatSetup();
    $items = [['description' => 'Aperçu', 'quantity' => 1, 'unit_amount' => 10000, 'vat_rate' => '20']];
    $r = $this->actingAs($user)->post(route('invoices.draft-preview'), invoicePayload($client, $items));
    $r->assertOk();
    expect($r->headers->get('Content-Type'))->toContain('application/pdf');
    $this->actingAs($user)->put(route('proposals.draft-preview'), ['name' => 'Aperçu', 'client_id' => $client->id, 'currency' => 'EUR', 'items' => $items])->assertOk();
    expect(Invoice::query()->withoutGlobalScopes()->where('company_id', $company->id)->count())->toBe(0)
        ->and(Proposal::query()->withoutGlobalScopes()->where('company_id', $company->id)->count())->toBe(0);
});

test('the shared editor renders for invoices and quotes with the new line tools', function () {
    [$company, $user, $client] = lineVatSetup();
    foreach ([route('invoices.create'), route('proposals.create')] as $url) {
        $this->actingAs($user)->get($url)->assertOk()
            ->assertSee('data-cf-doc-editor', false)
            ->assertSee('data-add-line', false)
            ->assertSee('data-quick-product-open', false)
            ->assertSee('data-quick-product-aside', false)
            ->assertSee('data-doc-editor-advanced', false)
            ->assertSee('data-doc-preview', false)
            ->assertSee("[vat_rate]", false)
            ->assertSee('name="client_id"', false);
    }
    $inv = legacyInvoice($company, $client);
    $this->actingAs($user)->get(route('invoices.edit', $inv))->assertOk()->assertSee('data-cf-doc-editor', false)->assertSee('name="number"', false);
});

test('AI chat rules understand per-line VAT requests', function () {
    $svc = app(AiChatService::class);
    $lines = [['description' => 'Formation Laravel', 'quantity' => 1, 'unit_minor' => 10000], ['description' => 'Livre', 'quantity' => 1, 'unit_minor' => 2000]];
    expect($svc->adjustIntentsFromRules('TVA 0 % sur la ligne 2', $lines))->toBe([['op' => 'vat', 'rate' => 0.0, 'line' => 1]])
        ->and($svc->adjustIntentsFromRules('mets la TVA à 19 %', $lines))->toBe([['op' => 'vat', 'rate' => 19.0]])
        ->and($svc->adjustIntentsFromRules('5,5 % de TVA sur le livre', $lines)[0])->toMatchArray(['op' => 'vat', 'rate' => 5.5, 'match' => 'livre'])
        ->and($svc->isAdjustRequest('tva 7 %'))->toBeTrue()
        ->and($svc->adjustIntentsFromRules('remise 10 %', $lines)[0]['op'])->toBe('discount');
});

test('client postal code is stored, shown and printed on documents', function () {
    [$company, $user] = lineVatSetup();
    $this->actingAs($user)->post(route('clients.store'), [
        'name' => 'Client CP', 'email' => 'cp@example.test', 'address_line1' => '3 avenue Habib Bourguiba', 'address_postal_code' => '1000', 'address_city' => 'Tunis', 'address_country' => 'TN',
    ])->assertSessionHasNoErrors();
    $c = Client::query()->withoutGlobalScopes()->where('company_id', $company->id)->where('name', 'Client CP')->firstOrFail();
    expect($c->address['postal_code'])->toBe('1000')->and($c->addressLines())->toContain('1000 Tunis');
    $this->actingAs($user)->get(route('clients.show', $c))->assertOk()->assertSee('1000 Tunis');
});

test('invoice import reads a per-line VAT rate column (Taux TVA) and keeps the default otherwise', function () {
    [$company, $user] = lineVatSetup();
    $csv = "N° facture,Client,Description,Quantité,Prix unitaire,Taux TVA\n"
        ."F-IMP-1,Client Import,Formation,1,100,20\n"
        ."F-IMP-1,Client Import,Livre,1,50,0\n";
    $s = app(DataImportService::class);
    $rows = $s->parseDelimited($csv);
    $auto = $s->classifyHeaders('invoices', array_keys($rows[0]))['mapping'];
    $map = array_map(fn ($h) => $auto[$h] ?? '', array_keys($rows[0]));
    expect($map[5])->toBe('vat_rate');

    $this->actingAs($user)->post(route('import.store', 'invoices'), ['raw' => $csv, 'mapping' => $map])->assertRedirect(route('import.index'));
    $inv = Invoice::query()->withoutGlobalScopes()->where('company_id', $company->id)->with('items')->firstOrFail();
    expect([(int) $inv->subtotal_amount, (int) $inv->vat_amount])->toBe([15000, 2000])
        ->and($inv->items->pluck('vat_rate')->map(fn ($v) => (float) $v)->sort()->values()->all())->toBe([0.0, 20.0]);
});
