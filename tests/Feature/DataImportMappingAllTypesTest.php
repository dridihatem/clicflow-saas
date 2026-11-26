<?php

use App\Enums\InvoiceStatus;
use App\Enums\ProspectStatus;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Proposal;
use App\Models\Prospect;
use App\Models\User;
use App\Services\DataImportService;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function mappingAdmin(): User
{
    $u = User::factory()->create();
    $u->assignRole('company_admin');

    return $u;
}

/** Auto-detected mapping as the preview pre-selects it, by column index. */
function autoMapping(string $type, string $csv): array
{
    $s = app(DataImportService::class);
    $rows = $s->parseDelimited($csv);
    $auto = $s->classifyHeaders($type, array_keys($rows[0]))['mapping'];

    return array_map(fn ($h) => $auto[$h] ?? '', array_keys($rows[0]));
}

test('french number and date parsing', function () {
    $s = app(DataImportService::class);
    expect($s->parseDecimal('1 234,56'))->toBe('1234.56')
        ->and($s->parseDecimal('1.234,56'))->toBe('1234.56')
        ->and($s->parseDecimal('1,234.56'))->toBe('1234.56')
        ->and($s->parseDecimal('12,5 €'))->toBe('12.5')
        ->and($s->parseDecimal("2\u{00A0}500"))->toBe('2500')
        ->and($s->parseDecimal(''))->toBeNull()
        ->and($s->normalizeDate('31/12/2026'))->toBe('2026-12-31')
        ->and($s->normalizeDate('2026-01-05'))->toBe('2026-01-05')
        ->and($s->normalizeDate('n/a'))->toBeNull()
        ->and($s->invoiceStatusFromText('Payée'))->toBe(InvoiceStatus::Paid)
        ->and($s->invoiceStatusFromText('Brouillon'))->toBe(InvoiceStatus::Draft);
});

test('invoice import with french headers, one row per invoice, creates missing client', function () {
    $u = mappingAdmin();
    $csv = "N° facture,Date facture,Client,Montant HT,TVA,Total TTC,Statut,Date d'échéance\n"
        ."F-001,15/01/2026,Société Alpha,\"1 234,56\",\"234,57\",\"1 469,13\",Payée,15/02/2026\n"
        ."F-002,20/01/2026,Société Alpha,2500,475,2975,Brouillon,20/02/2026\n";

    $map = autoMapping('invoices', $csv);
    expect($map)->toBe(['ref', 'issue_date', 'client_name', 'amount_ht', '', '', 'status', 'due_date']);

    $this->actingAs($u)->post(route('import.preview', 'invoices'), ['import_input' => $csv])->assertOk()
        ->assertSee('name="mapping[7]"', false)
        ->assertSee(__('data_import_confirm_invoices', ['count' => 2]));

    $this->actingAs($u)->post(route('import.store', 'invoices'), ['raw' => $csv, 'mapping' => $map])->assertRedirect(route('import.index'));

    $invoices = Invoice::query()->withoutGlobalScopes()->where('company_id', $u->company_id)->with('items')->orderBy('created_at')->get();
    expect($invoices)->toHaveCount(2);
    expect($invoices[0]->subtotal_amount)->toBe(123456)
        ->and($invoices[0]->status)->toBe(InvoiceStatus::Paid)
        ->and($invoices[0]->due_date->format('Y-m-d'))->toBe('2026-02-15')
        ->and($invoices[0]->created_at->format('Y-m-d'))->toBe('2026-01-15')
        ->and($invoices[0]->items->first()->description)->toContain('F-001')
        ->and($invoices[1]->subtotal_amount)->toBe(250000)
        ->and($invoices[1]->status)->toBe(InvoiceStatus::Draft);
    expect(Client::query()->withoutGlobalScopes()->where('company_id', $u->company_id)->where('name', 'Société Alpha')->count())->toBe(1);
});

test('invoice import groups multi-line rows and matches existing client case-insensitively', function () {
    $u = mappingAdmin();
    Client::factory()->create(['company_id' => $u->company_id, 'name' => 'Beta SARL', 'email' => null]);
    $csv = "N° facture,Client,Désignation,Quantité,Prix unitaire HT\n"
        ."F-10,beta sarl,Site web,1,\"1 500,00\"\n"
        ."F-10,beta sarl,Hébergement,2,\"120,50\"\n";

    $this->actingAs($u)->post(route('import.store', 'invoices'), ['raw' => $csv, 'mapping' => autoMapping('invoices', $csv)])->assertRedirect();

    $inv = Invoice::query()->withoutGlobalScopes()->where('company_id', $u->company_id)->with('items')->sole();
    expect($inv->items)->toHaveCount(2)->and($inv->subtotal_amount)->toBe(150000 + 2 * 12050);
    expect(Client::query()->withoutGlobalScopes()->where('company_id', $u->company_id)->count())->toBe(1);
});

test('invoice mapping requires client and line columns and rejects duplicates', function () {
    $u = mappingAdmin();
    $csv = "A,B\nx,10\n";
    $this->actingAs($u)->post(route('import.store', 'invoices'), ['raw' => $csv, 'mapping' => ['client_name', 'client_name']])
        ->assertOk()->assertSee(e(__('data_import_mapping_duplicate', ['field' => __('data_import_field_invoices_client_name')])), false)
        ->assertSee(e(__('data_import_mapping_need_lines')), false);
    expect(Invoice::query()->withoutGlobalScopes()->count())->toBe(0);
});

test('quote import with french headers', function () {
    $u = mappingAdmin();
    $csv = "N° devis,Client,Objet,Désignation,Qté,PU HT,Validité\n"
        ."D-1,Gamma,Refonte,Maquettes,1,\"900,00\",31/12/2026\n"
        ."D-1,Gamma,Refonte,Intégration,3,\"450,00\",31/12/2026\n";
    $map = autoMapping('proposals', $csv);
    expect($map)->toBe(['ref', 'client_name', 'name', 'description', 'quantity', 'unit_price', 'valid_until']);

    $this->actingAs($u)->post(route('import.store', 'proposals'), ['raw' => $csv, 'mapping' => $map])->assertRedirect();
    $p = Proposal::query()->withoutGlobalScopes()->where('company_id', $u->company_id)->sole();
    expect($p->name)->toBe('Refonte')->and($p->subtotal_amount)->toBe(90000 + 3 * 45000)
        ->and($p->valid_until->format('Y-m-d'))->toBe('2026-12-31');
});

test('product import with french headers, name from reference and non-destructive update', function () {
    $u = mappingAdmin();
    $csv = "Référence,Désignation,Prix HT,Stock,Catégorie\n"
        ."REF-1,Cahier A5,\"12,50\",120,Papeterie\n"
        ."REF-2,,\"1 299,00\",3,Informatique\n";
    $map = autoMapping('products', $csv);
    expect($map)->toBe(['sku', 'name', 'price', 'stock_qty', 'category']);

    $this->actingAs($u)->post(route('import.store', 'products'), ['raw' => $csv, 'mapping' => $map])->assertRedirect();
    $p1 = Product::query()->withoutGlobalScopes()->where('company_id', $u->company_id)->where('sku', 'REF-1')->sole();
    $p2 = Product::query()->withoutGlobalScopes()->where('company_id', $u->company_id)->where('sku', 'REF-2')->sole();
    expect($p1->base_price_minor)->toBe(1250)->and($p1->variants()->first()->stock_qty)->toBe(120)
        ->and($p2->name)->toBe('REF-2')->and($p2->base_price_minor)->toBe(129900);

    // Re-import without price/stock keeps existing values.
    $csv2 = "Référence,Désignation\nREF-1,Cahier A5 ligné\n";
    $this->actingAs($u)->post(route('import.store', 'products'), ['raw' => $csv2, 'mapping' => ['sku', 'name']])->assertRedirect();
    $p1->refresh();
    expect($p1->name)->toBe('Cahier A5 ligné')->and($p1->base_price_minor)->toBe(1250)->and($p1->variants()->first()->stock_qty)->toBe(120);
});

test('prospect import with french headers maps company, status and source', function () {
    $u = mappingAdmin();
    $csv = "Société,Contact,E-mail,Téléphone,Source,Statut\n"
        ."Delta SA,Nour Ben Ali,nour@delta.test,+216 20 111 222,Recommandation,Contacté\n"
        ."Epsilon,,,+216 20 333 444,Site web,Nouveau\n";
    $map = autoMapping('prospects', $csv);
    expect($map)->toBe(['company', 'name', 'email', 'phone', 'source', 'status']);

    $this->actingAs($u)->post(route('import.store', 'prospects'), ['raw' => $csv, 'mapping' => $map])->assertRedirect();
    $nour = Prospect::query()->withoutGlobalScopes()->where('company_id', $u->company_id)->where('email', 'nour@delta.test')->sole();
    expect($nour->company_name)->toBe('Delta SA')->and($nour->status)->toBe(ProspectStatus::Contacted)
        ->and($nour->source->value)->toBe('referral');
    $eps = Prospect::query()->withoutGlobalScopes()->where('company_id', $u->company_id)->where('name', 'Epsilon')->sole();
    expect($eps->company_name)->toBe('Epsilon')->and($eps->source->value)->toBe('website_form');

    // Re-import with empty phone keeps the existing phone.
    $csv2 = "E-mail,Téléphone\nnour@delta.test,\n";
    $this->actingAs($u)->post(route('import.store', 'prospects'), ['raw' => $csv2, 'mapping' => ['email', 'phone']])->assertRedirect();
    expect($nour->fresh()->phone)->toBe('+216 20 111 222')->and($nour->fresh()->company_name)->toBe('Delta SA');
});

test('sync path without mapping keeps working and does not create missing clients', function () {
    $u = mappingAdmin();
    $s = app(DataImportService::class)->createMissingClients(false);
    $company = $u->company;
    Client::factory()->create(['company_id' => $company->id, 'email' => 'qb@example.test']);

    $res = $s->importInvoices($company, [
        ['ref' => 'QB-1', 'client_email' => 'qb@example.test', 'status' => 'draft', 'description' => 'Item', 'quantity' => 2, 'unit_price' => 1500],
        ['ref' => 'QB-2', 'client_email' => 'missing@example.test', 'description' => 'Item', 'quantity' => 1, 'unit_price' => '10.00'],
    ]);
    expect($res['created'])->toBe(1)->and($res['errors'])->toHaveCount(1);
    $inv = Invoice::query()->withoutGlobalScopes()->where('company_id', $company->id)->sole();
    expect($inv->subtotal_amount)->toBe(2 * 150000);
    expect(Client::query()->withoutGlobalScopes()->where('company_id', $company->id)->count())->toBe(1);

    $p = $s->importProducts($company, [['name' => 'Widget', 'sku' => 'W-1', 'price' => 19.99, 'stock_qty' => 5]]);
    expect($p['created'])->toBe(1);
    expect(Product::query()->withoutGlobalScopes()->where('sku', 'W-1')->sole()->base_price_minor)->toBe(1999);
});
