<?php

namespace App\Services;

use App\Mail\ClientPortalAccessLinkMail;
use App\Mail\ClientPortalCredentialsMail;
use App\Models\Client;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Password;

class ClientCredentialsMailService
{
    public function __construct(
        private TenantSmtpMailer $tenantMailer,
    ) {}

    /** Sends the portal credentials to the client, using the workspace SMTP when configured. */
    public function send(Client $client, Company $company, string $plainPassword): void
    {
        $clientEmail = $client->email;
        if ($clientEmail === null || $clientEmail === '') {
            throw new \RuntimeException(__('Client has no email address.'));
        }

        $mailable = new ClientPortalCredentialsMail($client, $company, $plainPassword);
        $this->tenantMailer->send($company, $mailable, $clientEmail);
    }

    /** Sends the login URL and a set-password link (no plaintext password). */
    public function sendAccessLink(Client $client, Company $company, User $user): void
    {
        $email = (string) ($user->email ?: $client->email);
        if ($email === '') {
            throw new \RuntimeException(__('Client has no email address.'));
        }

        $token = Password::broker('admin_welcome')->createToken($user);
        $setPasswordUrl = flowdesk_tenant_url($company, route('password.reset', ['token' => $token], false)).'?email='.urlencode($email);
        $loginUrl = flowdesk_tenant_url($company, route('login', [], false));

        $mailable = new ClientPortalAccessLinkMail($client, $company, $email, $setPasswordUrl, $loginUrl);
        $this->tenantMailer->send($company, $mailable, $email);
    }
}
