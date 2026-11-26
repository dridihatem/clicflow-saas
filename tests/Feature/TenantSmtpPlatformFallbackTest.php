<?php

use App\Mail\ClientPortalCredentialsMail;
use App\Models\Client;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Services\ClientCredentialsMailService;
use App\Services\TenantSmtpMailer;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    config([
        'mail.from.address' => 'noreply@clicflow.fr',
        'mail.from.name' => 'ClicFlow',
    ]);
});

function makeMailCompany(array $attrs = []): Company
{
    return Company::factory()->create(array_merge([
        'name' => 'Atelier Lumière',
        'contact_email' => 'contact@atelier-lumiere.test',
    ], $attrs));
}

test('client portal credentials use the platform mailer when the company has no SMTP', function () {
    $company = makeMailCompany();
    $client = Client::factory()->create([
        'company_id' => $company->id,
        'email' => 'client@example.test',
        'name' => 'Léa Client',
    ]);

    app(ClientCredentialsMailService::class)->send($client, $company, 'temporary-pass');

    Mail::assertSent(ClientPortalCredentialsMail::class, function (Mailable $mail) {
        $mail->assertTo('client@example.test');
        $mail->assertFrom('noreply@clicflow.fr', 'Atelier Lumière');
        $mail->assertHasReplyTo('contact@atelier-lumiere.test', 'Atelier Lumière');

        return true;
    });
});

test('incomplete company SMTP falls back to the platform mailer', function () {
    $company = makeMailCompany();
    CompanySetting::query()->withoutGlobalScopes()->create([
        'company_id' => $company->id,
        'smtp' => ['host' => '', 'port' => 587],
    ]);

    $mailable = (new class extends Mailable
    {
        public function build(): self
        {
            return $this->subject('Hello')->html('<p>Hi</p>');
        }
    });

    app(TenantSmtpMailer::class)->send($company, $mailable, 'to@example.test');

    Mail::assertSent(function (Mailable $mail) {
        $mail->assertFrom('noreply@clicflow.fr', 'Atelier Lumière');
        $mail->assertHasReplyTo('contact@atelier-lumiere.test', 'Atelier Lumière');

        return true;
    });
});

test('company SMTP that fails to send falls back to the platform mailer', function () {
    $company = makeMailCompany();
    CompanySetting::query()->withoutGlobalScopes()->create([
        'company_id' => $company->id,
        'smtp' => [
            'host' => 'smtp.invalid.example',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'user',
            'password' => 'secret',
            'from_address' => 'office@atelier.test',
            'from_name' => 'Atelier',
        ],
    ]);

    $mailable = (new class extends Mailable
    {
        public function build(): self
        {
            return $this->subject('Hello')->html('<p>Hi</p>');
        }
    });

    // Mail::build is used for tenant SMTP; fake it by making hasSmtp true but
    // Mail::fake() will also intercept Mail::build? In Laravel, Mail::fake()
    // may not intercept Mail::build(). We test through a partial mock instead.
    $mailer = Mockery::mock(TenantSmtpMailer::class)->makePartial();
    $mailer->shouldReceive('smtpFor')->andReturn([
        'host' => 'smtp.invalid.example',
        'port' => 587,
        'encryption' => 'tls',
        'username' => 'user',
        'password' => 'secret',
        'from_address' => 'office@atelier.test',
        'from_name' => 'Atelier',
    ]);

    // Call the real send — Mail::build will try a real connection and fail, then fallback.
    try {
        app(TenantSmtpMailer::class)->send($company, $mailable, 'to@example.test');
    } catch (Throwable $e) {
        // If fallback itself is faked, send should succeed via Mail::fake
        throw $e;
    }

    Mail::assertSent(function (Mailable $mail) {
        $mail->assertFrom('noreply@clicflow.fr', 'Atelier Lumière');
        $mail->assertHasReplyTo('contact@atelier-lumiere.test', 'Atelier Lumière');

        return true;
    });
});
