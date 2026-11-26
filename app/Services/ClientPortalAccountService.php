<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ClientPortalAccountService
{
    public function __construct(
        private ClientCredentialsMailService $credentialsMail,
    ) {}

    public function generatePassword(int $length = 12): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
        $max = strlen($chars) - 1;
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, $max)];
        }

        return $password;
    }

    /**
     * Create the portal user for a client.
     *
     * @return array{user: User, password: string, mailed: bool, mail_error: ?string}
     */
    public function create(Client $client, Company $company, string $email, string $password, bool $sendCredentials, ?string $locale = null): array
    {
        if ($client->user) {
            throw ValidationException::withMessages([
                'email' => __('client_portal_already_exists'),
            ]);
        }

        $email = Str::lower(trim($email));
        if ($email === '') {
            throw ValidationException::withMessages([
                'email' => __('client_portal_email_required'),
            ]);
        }

        $existing = User::query()->where('email', $email)->exists();
        if ($existing) {
            throw ValidationException::withMessages([
                'email' => __('client_portal_email_taken'),
            ]);
        }

        $user = DB::transaction(function () use ($client, $company, $email, $password, $locale): User {
            if ((string) $client->email !== $email) {
                $client->update(['email' => $email]);
            }

            $user = User::query()->create([
                'name' => $client->name,
                'email' => $email,
                'password' => Hash::make($password),
                'company_id' => $company->id,
                'locale' => $locale ?: 'fr',
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();
            $user->assignRole('client');
            $client->update([
                'user_id' => $user->id,
                'portal_disabled_at' => null,
            ]);

            return $user;
        });

        $mail = $this->maybeSend($client->fresh(), $company, $password, $sendCredentials);

        return ['user' => $user, 'password' => $password, 'mailed' => $mail['mailed'], 'mail_error' => $mail['error']];
    }

    /**
     * @return array{password: string, mailed: bool, mail_error: ?string}
     */
    public function resetPassword(Client $client, Company $company, string $password, bool $sendCredentials): array
    {
        $user = $client->user;
        if (! $user) {
            throw ValidationException::withMessages([
                'password' => __('client_portal_no_account'),
            ]);
        }

        $user->forceFill(['password' => Hash::make($password)])->save();

        $mail = $this->maybeSend($client, $company, $password, $sendCredentials);

        return ['password' => $password, 'mailed' => $mail['mailed'], 'mail_error' => $mail['error']];
    }

    /**
     * Email the client a set-password link for their existing portal account.
     *
     * @return array{mailed: bool, mail_error: ?string}
     */
    public function sendAccessLink(Client $client, Company $company): array
    {
        $user = $client->user;
        if (! $user) {
            throw ValidationException::withMessages([
                'portal' => __('client_portal_no_account'),
            ]);
        }

        try {
            $this->credentialsMail->sendAccessLink($client, $company, $user);

            return ['mailed' => true, 'mail_error' => null];
        } catch (\Throwable $e) {
            report($e);

            return ['mailed' => false, 'mail_error' => $e->getMessage()];
        }
    }

    public function disable(Client $client): void
    {
        if (! $client->user) {
            throw ValidationException::withMessages([
                'portal' => __('client_portal_no_account'),
            ]);
        }

        $client->update(['portal_disabled_at' => now()]);
    }

    public function enable(Client $client): void
    {
        if (! $client->user) {
            throw ValidationException::withMessages([
                'portal' => __('client_portal_no_account'),
            ]);
        }

        $client->update(['portal_disabled_at' => null]);
    }

    /**
     * @return array{mailed: bool, error: ?string}
     */
    private function maybeSend(Client $client, Company $company, string $password, bool $send): array
    {
        if (! $send) {
            return ['mailed' => false, 'error' => null];
        }

        try {
            $this->credentialsMail->send($client, $company, $password);

            return ['mailed' => true, 'error' => null];
        } catch (\Throwable $e) {
            report($e);

            return ['mailed' => false, 'error' => $e->getMessage()];
        }
    }
}
