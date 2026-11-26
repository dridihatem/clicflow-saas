<?php

use App\Enums\InvoiceRecurrenceInterval;
use App\Enums\InvoiceStatus;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use App\Services\InvoiceMailService;
use App\Services\RecurringInvoiceService;
use App\Services\WorkspaceCalendarService;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('company user can save a monthly recurring invoice', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create(['company_id' => $user->company_id]);

    $this->actingAs($user)
        ->get(route('invoices.create'))
        ->assertOk()
        ->assertSee(__('invoice_recurrence.title'), false)
        ->assertSee(__('invoice_recurrence.month'), false)
        ->assertSee(__('invoice_recurrence.quarter'), false)
        ->assertSee(__('invoice_recurrence.semiannual'), false)
        ->assertSee(__('invoice_recurrence.year'), false)
        ->assertSee(__('invoice_recurrence.custom'), false);

    $this->actingAs($user)
        ->post(route('invoices.store'), [
            'client_mode' => 'pick',
            'client_id' => $client->id,
            'currency' => $user->company->default_currency ?? 'USD',
            'due_date' => '2026-09-24',
            'status' => InvoiceStatus::Draft->value,
            'recurrence_active' => '1',
            'recurrence_interval' => InvoiceRecurrenceInterval::Month->value,
            'items' => [
                ['description' => 'Retainer', 'quantity' => 1, 'unit_amount' => 50000],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $invoice = Invoice::query()->withoutGlobalScope('tenant')
        ->where('company_id', $user->company_id)
        ->latest()
        ->first();

    expect($invoice)->not->toBeNull()
        ->and($invoice->recurrence_active)->toBeTrue()
        ->and($invoice->recurrence_interval)->toBe(InvoiceRecurrenceInterval::Month)
        ->and($invoice->recurrence_every_months)->toBe(1)
        ->and($invoice->recurrence_next_on?->toDateString())->toBe('2026-10-24');
});

test('cron generates the next recurring invoice, emails the client, and lists it in the portal', function () {
    Carbon::setTestNow('2026-10-24 09:00:00');

    $sentIds = [];
    $this->mock(InvoiceMailService::class, function ($mock) use (&$sentIds): void {
        $mock->shouldReceive('send')->once()->andReturnUsing(function ($invoice) use (&$sentIds): void {
            $sentIds[] = (string) $invoice->id;
        });
    });

    $staff = User::factory()->create();
    $portalUser = User::factory()->create(['company_id' => $staff->company_id]);
    $portalUser->syncRoles(['client']);
    $client = Client::factory()->create([
        'company_id' => $staff->company_id,
        'user_id' => $portalUser->id,
        'email' => 'billing@client.test',
    ]);

    $schedule = Invoice::factory()->create([
        'company_id' => $staff->company_id,
        'client_id' => $client->id,
        'status' => InvoiceStatus::Sent,
        'due_date' => '2026-09-24',
        'recurrence_active' => true,
        'recurrence_interval' => InvoiceRecurrenceInterval::Month,
        'recurrence_every_months' => 1,
        'recurrence_next_on' => '2026-10-24',
        'amount' => 50000,
        'subtotal_amount' => 50000,
        'currency' => 'USD',
    ]);
    InvoiceItem::query()->withoutGlobalScopes()->create([
        'company_id' => $staff->company_id,
        'invoice_id' => $schedule->id,
        'description' => 'Monthly retainer',
        'quantity' => 1,
        'unit_amount' => 50000,
        'total_amount' => 50000,
    ]);

    $this->artisan('invoices:process-recurring')->assertSuccessful();

    $child = Invoice::query()->withoutGlobalScope('tenant')
        ->where('recurrence_parent_id', $schedule->id)
        ->first();

    expect($child)->not->toBeNull()
        ->and($child->status)->toBe(InvoiceStatus::Sent)
        ->and($child->due_date?->toDateString())->toBe('2026-10-24')
        ->and($child->client_id)->toBe($client->id);

    $schedule->refresh();
    expect($schedule->recurrence_next_on?->toDateString())->toBe('2026-11-24');

    expect($sentIds)->toBe([(string) $child->id]);

    $this->actingAs($portalUser)
        ->get(route('portal.payments.index'))
        ->assertOk()
        ->assertSee($child->number, false);

    $this->artisan('invoices:process-recurring')->assertSuccessful();
    expect(
        Invoice::query()->withoutGlobalScope('tenant')->where('recurrence_parent_id', $schedule->id)->count()
    )->toBe(1);
});

test('calendar includes upcoming recurring invoice dates', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create(['company_id' => $user->company_id]);
    $schedule = Invoice::factory()->create([
        'company_id' => $user->company_id,
        'client_id' => $client->id,
        'status' => InvoiceStatus::Sent,
        'number' => 'INV-RECUR-1',
        'due_date' => '2026-09-24',
        'recurrence_active' => true,
        'recurrence_interval' => InvoiceRecurrenceInterval::Quarter,
        'recurrence_every_months' => 3,
        'recurrence_next_on' => '2026-12-24',
        'amount' => 12000,
        'currency' => 'USD',
    ]);

    $events = app(WorkspaceCalendarService::class)->eventsForCompany(
        $user->company,
        Carbon::parse('2026-12-01'),
        Carbon::parse('2027-01-31'),
    );

    $match = collect($events)->first(
        fn (array $ev): bool => ($ev['id'] ?? '') === 'invoice-recurring-'.$schedule->id.'-2026-12-24'
    );

    expect($match)->not->toBeNull()
        ->and($match['type'])->toBe('invoice')
        ->and($match['date'])->toBe('2026-12-24');
});

test('custom interval uses the requested number of months', function () {
    $months = InvoiceRecurrenceInterval::Custom->months(8);

    expect($months)->toBe(8)
        ->and(app(RecurringInvoiceService::class)->addMonths(Carbon::parse('2026-01-31'), 1)->toDateString())
        ->toBe('2026-02-28');
});
