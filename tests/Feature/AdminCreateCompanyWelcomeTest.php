<?php

use App\Mail\AdminCompanyWelcomeMail;
use App\Models\Company;
use App\Models\User;
use App\Services\AdminCompanyWelcomeService;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    config(['flowdesk.tenant_base_domain' => 'clicflow.fr']);
});

function adminCompanyPayload(array $overrides = []): array
{
    return array_merge([
        'company_name' => 'Atelier Nord',
        'subdomain' => 'atelier-nord',
        'contact_email' => 'contact@atelier.test',
        'phone' => '+33123456789',
        'default_locale' => 'fr',
        'default_currency' => 'EUR',
        'owner_first_name' => 'Camille',
        'owner_last_name' => 'Martin',
        'admin_email' => 'camille@atelier.test',
        'generate_password' => '1',
        'include_password' => '0',
        'subscription_mode' => 'trial',
        'plan_locked' => '0',
    ], $overrides);
}

test('platform admin can view the create company form', function () {
    actingAsAdmin();

    $this->get(route('admin.companies.create'))
        ->assertOk()
        ->assertSee('name="owner_first_name"', false)
        ->assertSee('name="subdomain"', false)
        ->assertSee('name="generate_password"', false)
        ->assertSee('value="trial"', false)
        ->assertSee('value="fr"', false)
        ->assertSee('value="EUR"', false);
});

test('admin can create a company and a welcome email is sent with a set-password link', function () {
    actingAsAdmin();
    Mail::fake();

    $response = $this->post(route('admin.companies.store'), adminCompanyPayload());

    $company = Company::query()->where('subdomain', 'atelier-nord')->first();
    expect($company)->not->toBeNull()
        ->and($company->default_locale)->toBe('fr')
        ->and($company->default_currency)->toBe('EUR')
        ->and($company->name)->toBe('Atelier Nord');

    $user = User::query()->where('email', 'camille@atelier.test')->first();
    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Camille Martin')
        ->and($user->hasRole('company_admin'))->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->company_id)->toBe($company->id)
        ->and($user->locale)->toBe('fr');

    $response
        ->assertRedirect(route('admin.companies.show', $company))
        ->assertSessionHas('welcome_email', 'camille@atelier.test')
        ->assertSessionHas('welcome_url')
        ->assertSessionHas('welcome_password')
        ->assertSessionMissing('welcome_mail_warning');

    $sent = Mail::sent(AdminCompanyWelcomeMail::class);
    expect($sent)->not->toBeEmpty();
    $mail = $sent->first();
    expect($mail->owner->email)->toBe($user->email)
        ->and($mail->company->subdomain)->toBe($company->subdomain)
        ->and($mail->plainPassword)->toBeNull()
        ->and($mail->loginUrl)->toContain('atelier-nord')
        ->and($mail->resetUrl)->toContain('reset-password')
        ->and($mail->expireMinutes)->toBeGreaterThanOrEqual(60)
        ->and($mail->isTest)->toBeFalse();
});

test('welcome email includes the password only when requested', function () {
    actingAsAdmin();
    Mail::fake();

    $this->post(route('admin.companies.store'), adminCompanyPayload([
        'subdomain' => 'atelier-mdp',
        'admin_email' => 'mdp@atelier.test',
        'include_password' => '1',
        'generate_password' => '1',
    ]))->assertRedirect();

    Mail::assertSent(AdminCompanyWelcomeMail::class, function (AdminCompanyWelcomeMail $mail) {
        return is_string($mail->plainPassword) && strlen($mail->plainPassword) >= 8;
    });
});

test('the company is kept if the welcome email fails to send', function () {
    actingAsAdmin();

    $this->mock(AdminCompanyWelcomeService::class, function ($mock) {
        $mock->shouldReceive('send')->once()->andReturn(['sent' => false, 'error' => 'smtp down']);
        $mock->shouldReceive('loginUrl')->andReturn('https://atelier-fail.clicflow.fr/login');
    });

    $this->post(route('admin.companies.store'), adminCompanyPayload([
        'subdomain' => 'atelier-fail',
        'admin_email' => 'fail@atelier.test',
    ]))
        ->assertRedirect()
        ->assertSessionHas('welcome_mail_warning');

    expect(Company::query()->where('subdomain', 'atelier-fail')->exists())->toBeTrue();
    expect(User::query()->where('email', 'fail@atelier.test')->exists())->toBeTrue();
});

test('admin can resend the welcome email without including a password', function () {
    actingAsAdmin();
    Mail::fake();

    $company = Company::factory()->create([
        'name' => 'Espace Renvoyer',
        'subdomain' => 'espace-renvoyer',
        'slug' => 'espace-renvoyer',
        'default_locale' => 'fr',
        'default_currency' => 'EUR',
    ]);
    $owner = User::factory()->create([
        'email' => 'owner-renvoyer@atelier.test',
        'company_id' => $company->id,
        'name' => 'Owner Renvoyer',
    ]);
    $owner->assignRole('company_admin');

    $this->post(route('admin.companies.welcome', $company))
        ->assertRedirect(route('admin.companies.show', $company))
        ->assertSessionHas('status');

    Mail::assertSent(AdminCompanyWelcomeMail::class, function (AdminCompanyWelcomeMail $mail) use ($owner) {
        return $mail->owner->is($owner) && $mail->plainPassword === null && $mail->isTest === false;
    });
});

test('a soft-deleted subdomain cannot be reused', function () {
    actingAsAdmin();
    Mail::fake();

    $existing = Company::factory()->create([
        'subdomain' => 'deja-pris',
        'slug' => 'deja-pris',
    ]);
    $existing->delete();

    $this->post(route('admin.companies.store'), adminCompanyPayload([
        'subdomain' => 'deja-pris',
        'admin_email' => 'unique-sub@atelier.test',
    ]))->assertSessionHasErrors('subdomain');

    expect(User::query()->where('email', 'unique-sub@atelier.test')->exists())->toBeFalse();
});

test('the welcome email html contains the login url and the set-password button', function () {
    $company = new Company(['name' => 'Société Exemple', 'subdomain' => 'societe-exemple']);
    $owner = new User(['name' => 'Hatem Dridi', 'email' => 'dridihatem@gmail.com']);
    $mail = new AdminCompanyWelcomeMail(
        company: $company,
        owner: $owner,
        loginUrl: 'https://societe-exemple.clicflow.fr/login',
        resetUrl: 'https://societe-exemple.clicflow.fr/reset-password/jeton-exemple?email=dridihatem%40gmail.com',
        expireMinutes: 10080,
        plainPassword: null,
        isTest: true,
    );

    app()->setLocale('fr');
    $html = $mail->render();

    expect($html)
        ->toContain('https://societe-exemple.clicflow.fr/login')
        ->toContain(__('admin_welcome_mail_set_password'))
        ->toContain('Société Exemple')
        ->toContain('dridihatem@gmail.com');
});
