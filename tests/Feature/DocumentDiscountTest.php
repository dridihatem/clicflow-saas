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
use App\Services\InvoicePdfService;
use App\Services\InvoiceTotalsService;
use App\Services\ProposalPdfService;
use App\Services\RecurringInvoiceService;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function discountSetup(): array
{
    $company = Company::factory()->create(['default_currency' => 'EUR']);
    CompanySetting::query()->withoutGlobalScopes()->updateOrCreate(['company_id' => $company->id], ['billing' => ['vat_percent' => 20]]);
    $company->refresh();
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->syncRoles(['company_admin']);
    $client = Client::query()->withoutGlobalScopes()->create(['company_id' => $company->id, 'name' => 'Client Remise', 'email' => 'remise@example.test']);

    return [$company, $user, $client];
}

function quotePayload(Client $client, array $extra = []): array
{
    return $extra + [
        'name' => 'Devis remise',
        'client_id' => $client->id,
        'currency' => 'EUR',
        'items' => [
            ['description' => 'Atelier', 'quantity' => 2, 'unit_amount' => 30000],
            ['description' => 'Audit', 'quantity' => 1, 'unit_amount' => 40000],
        ],
    ];
}

test('totals service: no discount is identical to the legacy subtotal computation', function () {
    [$company] = discountSetup();
    $svc = app(InvoiceTotalsService::class);
    foreach ([0, 1, 999, 100000, 123457] as $lines) {
        $legacy = $svc->fromSubtotalMinor($lines, $company);
        $new = $svc->fromLinesMinor($lines, $company, null, null, 'EUR');
        expect([$new['subtotal'], $new['vat'], $new['stamp'], $new['total'], $new['discount']])
            ->toBe([$legacy['subtotal'], $legacy['vat'], $legacy['stamp'], $legacy['total'], 0]);
    }
});

test('totals service: percent and amount discounts apply before VAT and are clamped', function () {
    [$company] = discountSetup();
    $svc = app(InvoiceTotalsService::class);

    $p = $svc->fromLinesMinor(100000, $company, 'percent', '10', 'EUR');
    expect([$p['discount'], $p['subtotal'], $p['vat'], $p['total']])->toBe([10000, 90000, 18000, 108000]);

    $a = $svc->fromLinesMinor(100000, $company, 'amount', '50,50', 'EUR');
    expect([$a['discount'], $a['subtotal'], $a['vat']])->toBe([5050, 94950, 18990]);

    expect($svc->fromLinesMinor(10000, $company, 'amount', '500', 'EUR')['subtotal'])->toBe(0)
        ->and($svc->fromLinesMinor(10000, $company, 'percent', '150', 'EUR')['discount'])->toBe(10000)
        ->and($svc->fromLinesMinor(10000, $company, 'percent', '', 'EUR')['discount'])->toBe(0)
        ->and($svc->fromLinesMinor(10000, $company, 'bogus', '10', 'EUR')['discount'])->toBe(0);
});

test('regression: a quote and an invoice without discount keep the exact same totals and views', function () {
    [$company, $user, $client] = discountSetup();

    $this->actingAs($user)->post(route('proposals.store'), quotePayload($client))->assertSessionHasNoErrors();
    $q = Proposal::query()->withoutGlobalScopes()->where('company_id', $company->id)->firstOrFail();
    expect([(int) $q->subtotal_amount, (int) $q->vat_amount, (int) $q->amount, (int) $q->discount_amount, $q->discount_type])
        ->toBe([100000, 20000, 120000, 0, null])
        ->and($q->linesSubtotalMinor())->toBe(100000)
        ->and($q->allocatedVatMinorForLineHt(60000))->toBe(12000);

    $this->actingAs($user)->get(route('proposals.show', $q))->assertOk()->assertDontSee(__('Lines total (ex. VAT)'));
    $html = app(ProposalPdfService::class)->html($q->fresh());
    expect($html)->not->toContain(__('Lines total (ex. VAT)'))->toContain(__('Total HT'));

    // Legacy row inserted directly (as existing prod data): discount columns default to 0 / null.
    $inv = Invoice::factory()->create(['company_id' => $company->id, 'client_id' => $client->id, 'subtotal_amount' => 50000, 'vat_amount' => 10000, 'fiscal_stamp_amount' => 0, 'amount' => 60000, 'currency' => 'EUR']);
    $inv->refresh();
    expect((int) $inv->discount_amount)->toBe(0)->and($inv->hasDiscount())->toBeFalse()->and($inv->allocatedVatMinorForLineHt(25000))->toBe(5000);
    $this->actingAs($user)->get(route('invoices.show', $inv))->assertOk()->assertDontSee(__('Lines total (ex. VAT)'));
});

test('quote with a 10 % discount: stored totals, show page, PDF, portal and invoice conversion', function () {
    [$company, $user, $client] = discountSetup();

    $this->actingAs($user)->post(route('proposals.store'), quotePayload($client, ['discount_type' => 'percent', 'discount_value' => '10']))->assertSessionHasNoErrors();
    $q = Proposal::query()->withoutGlobalScopes()->where('company_id', $company->id)->firstOrFail();
    expect([(int) $q->discount_amount, (int) $q->subtotal_amount, (int) $q->vat_amount, (int) $q->amount])->toBe([10000, 90000, 18000, 108000])
        ->and($q->linesSubtotalMinor())->toBe(100000)
        ->and($q->discountRateLabel())->toBe('10 %');

    $this->actingAs($user)->get(route('proposals.show', $q))->assertOk()
        ->assertSee(__('Lines total (ex. VAT)'))->assertSee(__('Discount (:rate)', ['rate' => '10 %']))->assertSee(__('Net total (ex. VAT)'));
    $html = app(ProposalPdfService::class)->html($q->fresh());
    expect($html)->toContain(__('Discount (:rate)', ['rate' => '10 %']))->toContain(__('Net total HT'));

    // Edit keeps / changes the discount; clearing it restores plain totals.
    $this->actingAs($user)->put(route('proposals.update', $q), quotePayload($client, ['discount_type' => 'amount', 'discount_value' => '150']))->assertSessionHasNoErrors();
    $q->refresh();
    expect([(int) $q->discount_amount, (int) $q->subtotal_amount, (int) $q->amount])->toBe([15000, 85000, 102000]);

    $this->actingAs($user)->put(route('proposals.update', $q), quotePayload($client, ['discount_type' => '', 'discount_value' => '']))->assertSessionHasNoErrors();
    expect((int) $q->fresh()->discount_amount)->toBe(0)->and((int) $q->fresh()->amount)->toBe(120000);
    $this->actingAs($user)->put(route('proposals.update', $q), quotePayload($client, ['discount_type' => 'amount', 'discount_value' => '150']))->assertSessionHasNoErrors();
    $q->refresh();

    // Accepted quote → invoice carries the discount and identical totals.
    $q->update(['status' => ProposalStatus::Accepted]);
    $this->actingAs($user)->post(route('proposals.invoice', $q))->assertRedirect();
    $inv = Invoice::query()->withoutGlobalScope('tenant')->where('proposal_id', $q->id)->firstOrFail();
    expect([(int) $inv->discount_amount, $inv->discount_type, (int) $inv->subtotal_amount, (int) $inv->vat_amount, (int) $inv->amount])
        ->toBe([15000, 'amount', 85000, 17000, 102000]);
    $inv->load('items');
    expect((int) $inv->items->sum('total_amount'))->toBe($inv->linesSubtotalMinor());
    $ihtml = app(InvoicePdfService::class)->html($inv->fresh());
    expect($ihtml)->toContain(__('Lines total (ex. VAT)'))->toContain(__('Discount'));
});

test('invoice discount survives duplicate and recurring generation; invalid values are rejected', function () {
    [$company, $user, $client] = discountSetup();

    $this->actingAs($user)->post(route('invoices.store'), [
        'client_mode' => 'pick',
        'client_id' => $client->id,
        'currency' => 'EUR',
        'due_date' => '2026-12-24',
        'status' => InvoiceStatus::Draft->value,
        'recurrence_active' => '1',
        'recurrence_interval' => \App\Enums\InvoiceRecurrenceInterval::Month->value,
        'discount_type' => 'amount',
        'discount_value' => '50,50',
        'items' => [['description' => 'Retainer', 'quantity' => 1, 'unit_amount' => 100000]],
    ])->assertSessionHasNoErrors();
    $inv = Invoice::query()->withoutGlobalScope('tenant')->where('company_id', $company->id)->firstOrFail();
    expect([(int) $inv->discount_amount, (int) $inv->subtotal_amount, (int) $inv->vat_amount, (int) $inv->amount])->toBe([5050, 94950, 18990, 113940]);

    $this->actingAs($user)->post(route('invoices.duplicate', $inv))->assertRedirect();
    $dup = Invoice::query()->withoutGlobalScope('tenant')->where('company_id', $company->id)->where('id', '!=', $inv->id)->firstOrFail();
    expect([(int) $dup->discount_amount, (int) $dup->amount])->toBe([5050, 113940]);

    $child = app(RecurringInvoiceService::class)->generateNextOccurrence($inv->fresh());
    expect($child)->not->toBeNull()->and((int) $child->discount_amount)->toBe(5050)->and((int) $child->amount)->toBe(113940);

    $this->actingAs($user)->post(route('invoices.store'), [
        'client_mode' => 'pick', 'client_id' => $client->id, 'currency' => 'EUR', 'status' => InvoiceStatus::Draft->value,
        'discount_type' => 'percent', 'discount_value' => 'abc',
        'items' => [['description' => 'X', 'quantity' => 1, 'unit_amount' => 1000]],
    ])->assertSessionHasErrors('discount_value');
});

test('quote and invoice forms render the discount controls', function () {
    [$company, $user] = discountSetup();
    $this->actingAs($user)->get(route('proposals.create'))->assertOk()->assertSee('name="discount_type"', false)->assertSee('data-cf-discount', false);
    $this->actingAs($user)->get(route('invoices.create'))->assertOk()->assertSee('name="discount_value"', false);
});
