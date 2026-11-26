<?php

use App\Enums\ProductKind;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function bulkAdmin(Company $company, string $role = 'company_admin'): User
{
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->syncRoles([$role]);

    return $user;
}

function catalogItem(Company $company, string $name, ProductKind $kind = ProductKind::Product): Product
{
    $p = Product::query()->withoutGlobalScopes()->create([
        'company_id' => $company->id,
        'name' => $name,
        'kind' => $kind,
        'sku' => strtoupper(substr($name, 0, 3)).'-'.fake()->unique()->numerify('####'),
        'base_price_minor' => 1000,
        'currency' => 'EUR',
        'track_stock' => false,
        'is_active' => true,
    ]);
    ProductVariant::query()->withoutGlobalScopes()->create([
        'company_id' => $company->id,
        'product_id' => $p->id,
        'name' => 'Default',
        'sku' => $p->sku.'-V',
        'price_minor' => 1000,
    ]);

    return $p;
}

test('company admin bulk deletes products and services with their variants', function () {
    $company = Company::factory()->create();
    $admin = bulkAdmin($company);
    $a = catalogItem($company, 'Carnet');
    $b = catalogItem($company, 'Conseil', ProductKind::Service);
    $keep = catalogItem($company, 'Stylo');

    $this->actingAs($admin)
        ->delete(route('commerce.products.bulk-destroy'), ['ids' => [$a->id, $b->id]])
        ->assertRedirect(route('commerce.products.index'))
        ->assertSessionHas('status', fn ($msg) => str_contains($msg, '2'));

    expect(Product::withoutGlobalScopes()->whereIn('id', [$a->id, $b->id])->count())->toBe(0)
        ->and(ProductVariant::withoutGlobalScopes()->whereIn('product_id', [$a->id, $b->id])->count())->toBe(0)
        ->and(Product::withoutGlobalScopes()->find($keep->id))->not->toBeNull();
});

test('ids from another company are ignored', function () {
    $company = Company::factory()->create();
    $other = Company::factory()->create();
    $admin = bulkAdmin($company);
    $mine = catalogItem($company, 'Carnet');
    $foreign = catalogItem($other, 'Secret');

    $this->actingAs($admin)
        ->delete(route('commerce.products.bulk-destroy'), ['ids' => [$mine->id, $foreign->id]])
        ->assertRedirect();

    expect(Product::withoutGlobalScopes()->find($mine->id))->toBeNull()
        ->and(Product::withoutGlobalScopes()->find($foreign->id))->not->toBeNull();

    // Single delete on a foreign product is not found.
    $this->actingAs($admin)->delete(route('commerce.products.destroy', $foreign->id))->assertNotFound();
    expect(Product::withoutGlobalScopes()->find($foreign->id))->not->toBeNull();
});

test('users without catalog permission cannot bulk delete', function () {
    $company = Company::factory()->create();
    $member = bulkAdmin($company, 'team_member');
    $p = catalogItem($company, 'Carnet');

    $status = $this->actingAs($member)
        ->delete(route('commerce.products.bulk-destroy'), ['ids' => [$p->id]])
        ->status();
    expect($status)->toBeIn([302, 403]); // team area redirect or forbidden
    expect(Product::withoutGlobalScopes()->find($p->id))->not->toBeNull();
    auth()->logout();

    $this->delete(route('commerce.products.bulk-destroy'), ['ids' => [$p->id]]);
    expect(Product::withoutGlobalScopes()->find($p->id))->not->toBeNull();
});

test('items used in invoices are skipped and reported in French', function () {
    app()->setLocale('fr');
    $company = Company::factory()->create();
    $admin = bulkAdmin($company);
    $used = catalogItem($company, 'Formation');
    $free = catalogItem($company, 'Carnet');

    $invoice = Invoice::factory()->create(['company_id' => $company->id]);
    InvoiceItem::query()->withoutGlobalScopes()->create([
        'company_id' => $company->id,
        'invoice_id' => $invoice->id,
        'product_id' => $used->id,
        'description' => 'Formation',
        'quantity' => 1,
        'unit_amount' => 1000,
        'total_amount' => 1000,
    ]);

    $this->actingAs($admin)
        ->delete(route('commerce.products.bulk-destroy'), ['ids' => [$used->id, $free->id]])
        ->assertSessionHas('status', function ($msg) {
            fwrite(STDERR, "FLASH: $msg\n");

            return str_contains($msg, '1') && str_contains($msg, 'Formation')
                && (str_contains($msg, 'factures') || str_contains($msg, 'invoices'));
        });
    expect(__('commerce_bulk_skipped_invoices', ['count' => 2, 'names' => 'A, B'], 'fr'))->toBe('2 ignoré(s) car utilisé(s) dans des factures (A, B)');

    expect(Product::withoutGlobalScopes()->find($used->id))->not->toBeNull()
        ->and(Product::withoutGlobalScopes()->find($free->id))->toBeNull()
        ->and(InvoiceItem::withoutGlobalScopes()->where('invoice_id', $invoice->id)->count())->toBe(1);
});

test('select all matching filters deletes only that kind in this company', function () {
    $company = Company::factory()->create();
    $admin = bulkAdmin($company);
    $s1 = catalogItem($company, 'Conseil', ProductKind::Service);
    $s2 = catalogItem($company, 'Audit', ProductKind::Service);
    $prod = catalogItem($company, 'Carnet');
    $otherService = catalogItem(Company::factory()->create(), 'Ailleurs', ProductKind::Service);

    $this->actingAs($admin)
        ->delete(route('commerce.products.bulk-destroy'), ['all_matching' => 1, 'kind' => 'service'])
        ->assertRedirect(route('commerce.products.index', ['kind' => 'service']));

    expect(Product::withoutGlobalScopes()->whereIn('id', [$s1->id, $s2->id])->count())->toBe(0)
        ->and(Product::withoutGlobalScopes()->find($prod->id))->not->toBeNull()
        ->and(Product::withoutGlobalScopes()->find($otherService->id))->not->toBeNull();
});

test('index shows selection checkboxes and the bulk delete form', function () {
    $company = Company::factory()->create();
    $admin = bulkAdmin($company);
    catalogItem($company, 'Carnet');

    $this->actingAs($admin)->get(route('commerce.products.index'))
        ->assertOk()
        ->assertSee('products-bulk-delete-form', false)
        ->assertSee(__('commerce_bulk_delete_button'));
});
