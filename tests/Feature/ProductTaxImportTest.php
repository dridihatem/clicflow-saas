<?php

use App\Enums\ProductKind;
use App\Models\Company;
use App\Models\Product;
use App\Models\User;
use App\Services\DataImportService;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function taxImportAdmin(?Company $company = null): User
{
    $company ??= Company::factory()->create(['default_currency' => 'TND']);
    $u = User::factory()->create(['company_id' => $company->id]);
    $u->syncRoles(['company_admin']);

    return $u;
}

function hatemSampleCsv(): string
{
    return (string) file_get_contents(base_path('tests/Fixtures/products-import-hatem-sample.csv'));
}

test('multi-line quoted descriptions stay in one row', function () {
    $s = app(DataImportService::class);
    $rows = $s->parseDelimited(hatemSampleCsv());

    expect($rows)->toHaveCount(4)
        ->and($rows[0]['désignation'])->toBe('CLUB ALTERA — MENTORING')
        ->and($rows[0]['prix_unitaire'])->toBe('€150,00')
        ->and(substr_count($rows[0]['description'], "\n"))->toBe(6)
        ->and($rows[3]['prix_unitaire'])->toBe('€1.200,00');
});

test('semicolon csv with BOM and CRLF is parsed', function () {
    $raw = "\xEF\xBB\xBFDésignation;Prix unitaire;Taxe 1\r\n\"Audit\r\nSEO\";\"1 500,00 DT\";19%\r\n";
    $rows = app(DataImportService::class)->parseDelimited($raw);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['désignation'])->toBe("Audit\nSEO")
        ->and($rows[0]['prix_unitaire'])->toBe('1 500,00 DT');
});

test('price and tax parsing handles currency symbols and french formats', function () {
    $s = app(DataImportService::class);
    expect($s->parseDecimal('€150,00'))->toBe('150.00')
        ->and($s->parseDecimal('€1.200,00'))->toBe('1200.00')
        ->and($s->parseDecimal("1\u{00A0}234,50 TND"))->toBe('1234.50')
        ->and($s->parseDecimal('1 800,000 DT'))->toBe('1800.000')
        ->and($s->parseDecimal('$20.83'))->toBe('20.83')
        ->and($s->parseTaxRate('20.00%'))->toBe(20.0)
        ->and($s->parseTaxRate('5,5 %'))->toBe(5.5)
        ->and($s->parseTaxRate('0.00%'))->toBe(0.0)
        ->and($s->parseTaxRate('0.19'))->toBe(19.0)
        ->and($s->parseTaxRate(''))->toBeNull()
        ->and($s->parseTaxRate('150%'))->toBeNull();
});

test('his headers are auto-mapped including both taxes and group', function () {
    $s = app(DataImportService::class);
    $rows = $s->parseDelimited(hatemSampleCsv());
    $mapping = $s->classifyHeaders('products', array_keys($rows[0]))['mapping'];

    expect($mapping)->toMatchArray([
        'désignation' => 'name',
        'description' => 'description',
        'prix_unitaire' => 'price',
        'taxe_1' => 'tax_rate',
        'taxe_2' => 'tax_rate_2',
        'unité' => 'unit',
        'nom_du_groupe' => 'category',
    ]);
});

test('import stores price, tax rates and multi-line description as services', function () {
    $u = taxImportAdmin();
    $s = app(DataImportService::class);
    $csv = hatemSampleCsv();
    $rows = $s->parseDelimited($csv);
    $auto = $s->classifyHeaders('products', array_keys($rows[0]))['mapping'];
    $mapping = array_map(fn ($h) => $auto[$h] ?? '', array_keys($rows[0]));

    $html = $this->actingAs($u)->post(route('import.preview', 'products'), ['import_input' => $csv])
        ->assertOk()
        ->assertSee('20 %')
        ->assertSee('0 %')
        ->getContent();
    expect(preg_match('/150[.,]00 EUR/u', $html))->toBe(1)
        ->and(preg_match('/1[^0-9<]{0,8}200[.,]00 EUR/u', $html))->toBe(1);

    $this->actingAs($u)->post(route('import.store', 'products'), ['raw' => $csv, 'mapping' => $mapping])->assertRedirect();

    $items = Product::query()->withoutGlobalScopes()->where('company_id', $u->company_id)->get()->keyBy('name');
    expect($items)->toHaveCount(4);

    $club = $items['CLUB ALTERA — MENTORING'];
    expect($club->base_price_minor)->toBe(flowdesk_decimal_to_minor('150.00', 'EUR'))
        ->and($club->currency)->toBe('EUR')
        ->and((float) $club->tax_rate)->toBe(20.0)
        ->and((float) $club->tax_rate_2)->toBe(0.0)
        ->and($club->kind)->toBe(ProductKind::Service)
        ->and($club->track_stock)->toBeFalse()
        ->and($club->unit)->toBe('hour')
        ->and(substr_count((string) $club->description, "\n"))->toBe(6);

    expect($items['Création de site internet']->base_price_minor)->toBe(flowdesk_decimal_to_minor('1200.00', 'EUR'))
        ->and((float) $items['BOOST FACEBOOK  PREMIUM']->tax_rate)->toBe(0.0);

    // Re-import updates by name instead of duplicating.
    $this->actingAs($u)->post(route('import.store', 'products'), ['raw' => $csv, 'mapping' => $mapping])->assertRedirect();
    expect(Product::query()->withoutGlobalScopes()->where('company_id', $u->company_id)->count())->toBe(4);
});

test('same name twice in one file creates two items, re-import updates them', function () {
    $u = taxImportAdmin();
    $csv = "Désignation,Prix unitaire,Taxe 1\n\"Création QR code\",\"€30,00\",20.00%\n\"Création QR code\",\"€120,00\",20.00%\n";
    $mapping = ['name', 'price', 'tax_rate'];
    $this->actingAs($u)->post(route('import.store', 'products'), ['raw' => $csv, 'mapping' => $mapping])->assertRedirect();
    $this->actingAs($u)->post(route('import.store', 'products'), ['raw' => $csv, 'mapping' => $mapping])->assertRedirect();

    $prices = Product::query()->withoutGlobalScopes()->where('company_id', $u->company_id)->orderBy('base_price_minor')->pluck('base_price_minor')->all();
    expect($prices)->toBe([flowdesk_decimal_to_minor('30.00', 'EUR'), flowdesk_decimal_to_minor('120.00', 'EUR')]);
});

test('product form saves and shows tax rates in list and catalog payload', function () {
    $u = taxImportAdmin();
    $this->actingAs($u)->post(route('commerce.products.store'), [
        'name' => 'Accompagnement test',
        'kind' => 'service',
        'base_price' => '150',
        'currency' => 'EUR',
        'tax_rate' => '20',
        'tax_rate_2' => '1',
        'category' => 'Coaching',
    ])->assertRedirect();

    $p = Product::query()->withoutGlobalScopes()->where('company_id', $u->company_id)->firstOrFail();
    expect((float) $p->tax_rate)->toBe(20.0)
        ->and((float) $p->tax_rate_2)->toBe(1.0)
        ->and($p->category)->toBe('Coaching')
        ->and($p->taxLabel())->toBe('20 % + 1 %');

    $this->actingAs($u)->get(route('commerce.products.index'))->assertOk()->assertSee('20 % + 1 %');
    $this->actingAs($u)->get(route('commerce.products.show', $p))->assertOk()->assertSee('name="tax_rate"', false);

    $this->actingAs($u)->put(route('commerce.products.update', $p), [
        'name' => 'Accompagnement test', 'kind' => 'service', 'base_price' => '150', 'tax_rate' => '', 'tax_rate_2' => '',
    ])->assertRedirect();
    expect($p->fresh()->tax_rate)->toBeNull();

    $p->update(['tax_rate' => 19]);
    $payload = flowdesk_document_catalog_for_js([$p->fresh()]);
    expect($payload[0]['tax_rate'])->toBe(19.0)->and($payload[0]['tax_label'])->toBe('19 %');
});

test('mcp list_products and create_product expose tax rates', function () {
    $company = Company::factory()->create(['default_currency' => 'EUR']);
    $tools = app(\App\Services\Mcp\CompanyMcpTools::class);
    $call = function (string $method, array $args) use ($tools, $company) {
        $m = new ReflectionMethod($tools, $method);

        return $m->invoke($tools, $company, $args);
    };

    $created = $call('createProduct', ['name' => 'Audit', 'price' => 150, 'tax_rate' => 20]);
    expect($created['product']['tax_rate'])->toBe(20.0)->and($created['product']['kind'])->toBe('service');

    $list = $call('listProducts', ['q' => 'Audit']);
    expect(json_encode($list))->toContain('"tax_rate":20');
});
