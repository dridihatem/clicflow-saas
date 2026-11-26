<?php

use App\Enums\InvoiceStatus;
use App\Jobs\SendInvoiceReminderJob;
use App\Jobs\SendTenantMailJob;
use App\Mail\InvoiceSentMail;
use App\Models\Client;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Invoice;
use App\Services\InvoiceMailService;
use App\Services\TenantSmtpMailer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config(['mail.from.address' => 'noreply@clicflow.fr', 'mail.from.name' => 'ClicFlow']);
});

function queuedTestMailable(): Mailable
{
    return new class extends Mailable implements ShouldQueue
    {
        public function build(): self
        {
            return $this->subject('Hello')->html('<p>Hi</p>');
        }
    };
}

function qCompany(): Company
{
    return Company::factory()->create(['name' => 'Atelier Lumière', 'contact_email' => 'contact@atelier-lumiere.test']);
}

function qSmtp(Company $company, array $smtp): void
{
    CompanySetting::query()->withoutGlobalScopes()->create(['company_id' => $company->id, 'smtp' => $smtp]);
}

function overdueInvoice(Company $company, ?string $email = 'client@example.test', InvoiceStatus $status = InvoiceStatus::Sent): Invoice
{
    $client = Client::factory()->create(['company_id' => $company->id, 'email' => $email, 'name' => 'Léa']);

    return Invoice::factory()->create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'status' => $status,
        'due_date' => now()->subDays(5),
        'reminder_sent_at' => null,
    ]);
}

test('queued mailables are pushed as a tenant job without any SMTP secret in the payload', function () {
    Queue::fake();
    config(['queue.default' => 'redis']);
    $company = qCompany();
    qSmtp($company, ['host' => 'smtp.example.test', 'port' => 587, 'username' => 'u', 'password' => 'TopSecretPw-123', 'from_address' => 'office@atelier.test']);

    $invoice = overdueInvoice($company);
    app(InvoiceMailService::class)->send($invoice->load('client'), $company, false);

    Queue::assertPushed(SendTenantMailJob::class, function (SendTenantMailJob $job) use ($company) {
        expect($job->mailable)->toBeInstanceOf(InvoiceSentMail::class);
        $payload = serialize($job);
        expect($payload)->not->toContain('TopSecretPw-123')
            ->and($payload)->not->toContain('smtp.example.test')
            ->and($job->companyId)->toBe((string) $company->id)
            ->and($job->mailable->from)->toBe([]);

        return true;
    });
    Queue::assertNotPushed(\Illuminate\Mail\SendQueuedMailable::class);
});

test('the tenant mail job resolves the platform fallback at send time', function () {
    Mail::fake();
    $company = qCompany();
    $job = new SendTenantMailJob((string) $company->id, queuedTestMailable(), 'to@example.test');
    $job->handle(app(TenantSmtpMailer::class));

    Mail::assertSent(Mailable::class, function (Mailable $mail) {
        $mail->assertTo('to@example.test');
        $mail->assertFrom('noreply@clicflow.fr', 'Atelier Lumière');
        $mail->assertHasReplyTo('contact@atelier-lumiere.test', 'Atelier Lumière');
        expect($mail->from)->toHaveCount(1);

        return true;
    });
});

test('a failing company SMTP falls back to the platform identity only (single From)', function () {
    Mail::fake();
    $company = qCompany();
    qSmtp($company, ['host' => '127.0.0.1', 'port' => 1, 'encryption' => null, 'from_address' => 'office@atelier.test', 'from_name' => 'Atelier']);

    $job = new SendTenantMailJob((string) $company->id, queuedTestMailable(), 'to@example.test');
    $job->handle(app(TenantSmtpMailer::class));

    Mail::assertSent(Mailable::class, function (Mailable $mail) {
        expect($mail->from)->toHaveCount(1)
            ->and($mail->from[0]['address'])->toBe('noreply@clicflow.fr');

        return true;
    });
});

test('tenant mail job retries three times with backoff and logs per tenant on failure', function () {
    Log::spy();
    $company = qCompany();
    $job = new SendTenantMailJob((string) $company->id, queuedTestMailable(), 'to@example.test');
    expect($job->tries)->toBe(3)->and($job->backoff())->toBe([60, 300, 900]);

    $job->failed(new RuntimeException('boom'));
    Log::shouldHaveReceived('warning')->withArgs(fn ($msg, $ctx) => $msg === 'tenant_mail_job_failed' && $ctx['company_id'] === (string) $company->id)->once();
});

test('invoice reminder is marked only after the email was actually sent', function () {
    Mail::fake();
    $company = qCompany();
    $invoice = overdueInvoice($company);

    $this->artisan('invoices:send-reminders')->assertSuccessful();

    Mail::assertSent(InvoiceSentMail::class, fn ($m) => $m->hasTo('client@example.test'));
    $invoice->refresh();
    expect($invoice->reminder_sent_at)->not->toBeNull()
        ->and($invoice->status)->toBe(InvoiceStatus::Overdue);
});

test('a failed reminder send leaves the invoice unmarked and rethrows for retry', function () {
    $company = qCompany();
    $invoice = overdueInvoice($company);

    $mailer = Mockery::mock(InvoiceMailService::class);
    $mailer->shouldReceive('send')->once()->andThrow(new RuntimeException('smtp down'));

    $job = new SendInvoiceReminderJob((string) $invoice->id);
    expect(fn () => $job->handle($mailer, app(\App\Services\FcmPushService::class)))->toThrow(RuntimeException::class);

    $invoice->refresh();
    expect($invoice->reminder_sent_at)->toBeNull()
        ->and($invoice->status)->toBe(InvoiceStatus::Sent)
        ->and($job->tries)->toBe(3)
        ->and($job->backoff())->toBe([60, 300, 900]);

    Log::spy();
    $job->failed(new RuntimeException('smtp down'));
    Log::shouldHaveReceived('warning')->withArgs(fn ($msg, $ctx) => $msg === 'invoice_reminder_failed' && $ctx['company_id'] === (string) $company->id)->once();
});

test('the reminder job delivers synchronously inside the worker (no nested queueing)', function () {
    Queue::fake([SendTenantMailJob::class]);
    Mail::fake();
    config(['queue.default' => 'redis']);
    $company = qCompany();
    $invoice = overdueInvoice($company);

    (new SendInvoiceReminderJob((string) $invoice->id))->handle(app(InvoiceMailService::class), app(\App\Services\FcmPushService::class));

    Queue::assertNotPushed(SendTenantMailJob::class);
    Mail::assertSent(InvoiceSentMail::class);
    expect($invoice->refresh()->reminder_sent_at)->not->toBeNull();
});

test('reminder command queues one job per eligible invoice and skips clients without email', function () {
    Queue::fake();
    Log::spy();
    $company = qCompany();
    $ok = overdueInvoice($company);
    overdueInvoice($company, null);
    $paid = overdueInvoice($company, 'paid@example.test', InvoiceStatus::Paid);

    $this->artisan('invoices:send-reminders')->assertSuccessful();

    Queue::assertPushed(SendInvoiceReminderJob::class, 1);
    Queue::assertPushed(SendInvoiceReminderJob::class, fn ($j) => $j->invoiceId === (string) $ok->id);
    Log::shouldHaveReceived('warning')->withArgs(fn ($msg, $ctx) => $msg === 'invoice_reminders_skipped_no_client_email' && $ctx['count'] === 1)->once();
    expect($ok->refresh()->reminder_sent_at)->toBeNull();
});

test('reminder job skips invoices that were paid or already reminded meanwhile', function () {
    Mail::fake();
    $company = qCompany();
    $invoice = overdueInvoice($company);
    $invoice->update(['status' => InvoiceStatus::Paid]);

    (new SendInvoiceReminderJob((string) $invoice->id))->handle(app(InvoiceMailService::class), app(\App\Services\FcmPushService::class));

    Mail::assertNothingSent();
});
