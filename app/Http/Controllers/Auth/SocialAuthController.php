<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

class SocialAuthController extends Controller
{
    private const PROVIDER_MAP = [
        'github' => 'github',
        'google' => 'google',
        'linkedin' => 'linkedin-openid',
    ];

    private const MOBILE_SCHEME = 'clicflow://oauth';

    public function redirect(Request $request, string $provider)
    {
        $driver = $this->driverName($provider);

        if ($request->query('client') === 'mobile' || $request->boolean('mobile')) {
            $request->session()->put('oauth_client', 'mobile');
        } else {
            $request->session()->forget('oauth_client');
        }

        return Socialite::driver($driver)->redirect();
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
        $driver = $this->driverName($provider);
        $mobile = $request->session()->pull('oauth_client') === 'mobile';

        try {
            /** @var SocialiteUser $socialUser */
            $socialUser = Socialite::driver($driver)->user();
        } catch (\Throwable $e) {
            return $this->oauthFailure(
                $mobile,
                __('Social sign-in failed. Please try again.'),
            );
        }

        $email = $socialUser->getEmail();

        if ($email === null || $email === '') {
            return $this->oauthFailure(
                $mobile,
                __('This provider did not return an email. Use another login method.'),
            );
        }

        $existingAccount = SocialAccount::query()
            ->where('provider', $driver)
            ->where('provider_user_id', (string) $socialUser->getId())
            ->first();

        if ($existingAccount) {
            return $this->completeLogin($existingAccount->user, $mobile);
        }

        $userByEmail = User::query()->where('email', $email)->first();

        if ($userByEmail) {
            $this->attachAccount($userByEmail, $driver, $socialUser);

            return $this->completeLogin($userByEmail, $mobile);
        }

        if ($mobile) {
            return $this->oauthFailure(
                true,
                __('No company workspace account found for this social login. Sign in with email, or connect GitHub/LinkedIn on the web profile first.'),
            );
        }

        session([
            'oauth_pending' => [
                'driver' => $driver,
                'provider_label' => $provider,
                'provider_user_id' => (string) $socialUser->getId(),
                'email' => $email,
                'name' => $socialUser->getName()
                    ?? $socialUser->getNickname()
                    ?? Str::before($email, '@'),
                'avatar_url' => $socialUser->getAvatar(),
            ],
        ]);

        return redirect()->route('oauth.company.create');
    }

    private function completeLogin(User $user, bool $mobile = false): RedirectResponse
    {
        if ($mobile) {
            return $this->completeMobileLogin($user);
        }

        Auth::login($user);

        if ($user->hasTwoFactorEnabled()) {
            Auth::logout();
            request()->session()->put('login.id', $user->id);
            request()->session()->put('login.remember', false);
            request()->session()->regenerate();

            return redirect()->route('two-factor.login');
        }

        return flowdesk_redirect_after_auth($user, request());
    }

    private function completeMobileLogin(User $user): RedirectResponse
    {
        if (! $user->hasAnyRole(['company_admin', 'team_member']) || ! $user->company) {
            return $this->oauthFailure(
                true,
                __('This social account is not linked to a company workspace user.'),
            );
        }

        if ($user->hasTwoFactorEnabled()) {
            return $this->oauthFailure(
                true,
                __('Two-factor authentication is enabled. Sign in on the web workspace first.'),
            );
        }

        $token = $user->createToken('mobile-oauth')->plainTextToken;

        return redirect()->away(self::MOBILE_SCHEME.'?token='.urlencode($token));
    }

    private function oauthFailure(bool $mobile, string $message): RedirectResponse
    {
        if ($mobile) {
            return redirect()->away(self::MOBILE_SCHEME.'?error='.urlencode($message));
        }

        return redirect()
            ->route('login')
            ->withErrors(['email' => $message]);
    }

    private function attachAccount(User $user, string $driver, SocialiteUser $socialUser): void
    {
        SocialAccount::query()->updateOrCreate(
            [
                'provider' => $driver,
                'provider_user_id' => (string) $socialUser->getId(),
            ],
            [
                'user_id' => $user->id,
                'email' => $socialUser->getEmail(),
                'avatar_url' => $socialUser->getAvatar(),
            ],
        );
    }

    private function driverName(string $provider): string
    {
        if (! isset(self::PROVIDER_MAP[$provider])) {
            abort(404);
        }

        return self::PROVIDER_MAP[$provider];
    }
}
