<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use App\Services\CompanyNamingService;
use App\Services\LocaleDetectionService;
use App\Services\PlanLimitService;
use App\Services\SubscriptionBootstrapService;
use App\Services\TenantStorageService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $email = strtolower(trim($data['email']));

        /** @var User|null $user */
        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if (! $user || ! Hash::check($data['password'], (string) $user->password)) {
            return response()->json([
                'message' => __('auth.failed'),
            ], 401);
        }

        $isStaff = $user->hasAnyRole(['company_admin', 'team_member'], 'web')
            || $user->roles()->whereIn('name', ['company_admin', 'team_member'])->exists();

        if (! $isStaff) {
            return response()->json([
                'message' => __('These credentials are not authorized for the company app. Use a company admin or team member account.'),
            ], 403);
        }

        $company = $user->company_id
            ? Company::query()->withTrashed()->find($user->company_id)
            : null;

        if (! $company || $company->trashed()) {
            return response()->json([
                'message' => __('Your account is not linked to an active company workspace.'),
            ], 403);
        }

        if (method_exists($user, 'hasTwoFactorEnabled') && $user->hasTwoFactorEnabled()) {
            return response()->json([
                'message' => __('Two-factor authentication is enabled. Sign in on the web workspace to continue.'),
                'requires_2fa' => true,
            ], 403);
        }

        $token = $user->createToken($data['device_name'] ?? 'mobile')->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->userPayload($user),
            'ai_credits' => $this->aiCreditsPayload($user->company),
        ]);
    }

    /**
     * Create a company workspace + company_admin user (no CAPTCHA on mobile).
     */
    public function register(Request $request): JsonResponse
    {
        if ($request->input('country') === '') {
            $request->merge(['country' => null]);
        }

        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'country' => ['nullable', 'string', 'size:2', Rule::in(array_keys(config('flowdesk_countries', [])))],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $naming = app(CompanyNamingService::class);
        $tenantStorage = app(TenantStorageService::class);

        $attempt = 0;
        while (true) {
            try {
                [$company, $user, $token] = DB::transaction(function () use ($request, $data, $naming, $tenantStorage) {
            $subdomain = $naming->uniqueSubdomain($data['company_name']);
            $slug = $naming->uniqueSlug($data['company_name']);

            $country = ! empty($data['country']) ? strtoupper((string) $data['country']) : null;
            $defaultCurrency = ! empty($data['default_currency'])
                ? strtoupper((string) $data['default_currency'])
                : flowdesk_default_currency();

            $defaultLocale = app(LocaleDetectionService::class)
                ->defaultLocaleForRegistration($request, $country);

            $company = Company::query()->create([
                'name' => $data['company_name'],
                'contact_email' => $data['email'],
                'subdomain' => $subdomain,
                'slug' => $slug,
                'country' => $country,
                'default_currency' => $defaultCurrency,
                'default_locale' => $defaultLocale,
            ]);

            $tenantStorage->bootstrap($company);
            app(SubscriptionBootstrapService::class)->ensureDefaultSubscription($company);
            $company->regenerateApiToken();

            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'company_id' => $company->id,
                'locale' => $defaultLocale,
            ]);

            $user->assignRole('company_admin');

            $token = $user->createToken($data['device_name'] ?? 'mobile')->plainTextToken;

            return [$company, $user, $token];
        });
                break;
            } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
                if (++$attempt < 3 && ! str_contains($e->getMessage(), 'users_email_unique')) {
                    continue;
                }
                report($e);

                return response()->json([
                    'message' => str_contains($e->getMessage(), 'users_email_unique')
                        ? __('This email address is already used by another account.')
                        : __('This company name is already taken. Please try a variation (e.g. add your city).'),
                ], 422);
            } catch (\Illuminate\Database\QueryException $e) {
                report($e);

                return response()->json(['message' => __('Something went wrong while creating your workspace. Please try again.')], 500);
            }
        }

        event(new Registered($user));

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->userPayload($user->fresh()),
            'ai_credits' => $this->aiCreditsPayload($company),
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'subdomain' => $company->subdomain,
            ],
        ], 201);
    }

    public function me(Request $request, PlanLimitService $planLimits): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user' => $this->userPayload($user),
            'ai_credits' => $this->aiCreditsPayload($user?->company, $planLimits),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['ok' => true]);
    }

    public function updateLocale(Request $request): JsonResponse
    {
        $allowed = ['en', 'fr', 'es', 'ar'];
        $data = $request->validate([
            'locale' => ['required', 'string', 'in:'.implode(',', $allowed)],
        ]);

        $user = $request->user();
        $user->forceFill(['locale' => $data['locale']])->save();

        app()->setLocale($data['locale']);

        return response()->json([
            'user' => $this->userPayload($user->fresh()),
        ]);
    }

    /**
     * Start URLs for web Socialite providers configured in the backend.
     * Mobile opens these in an auth session; callback returns clicflow://oauth?token=…
     */
    public function oauthProviders(): JsonResponse
    {
        $catalog = [
            'google' => [
                'label' => 'Google',
                'service' => 'google',
                'url' => url('/auth/google/redirect?client=mobile'),
            ],
            'github' => [
                'label' => 'GitHub',
                'service' => 'github',
                'url' => url('/auth/github/redirect?client=mobile'),
            ],
            'linkedin' => [
                'label' => 'LinkedIn',
                // Laravel Socialite OpenID config key (see SocialAuthController).
                'service' => 'linkedin-openid',
                'url' => url('/auth/linkedin/redirect?client=mobile'),
            ],
        ];

        $providers = [];
        foreach ($catalog as $key => $meta) {
            $serviceKeys = array_values(array_unique(array_filter([
                $meta['service'] ?? null,
                // LinkedIn OpenID may also be exposed under the legacy `linkedin` key.
                $key === 'linkedin' ? 'linkedin' : null,
            ])));
            $configured = false;
            foreach ($serviceKeys as $serviceKey) {
                $cfg = config('services.'.$serviceKey);
                $clientId = is_array($cfg) ? trim((string) ($cfg['client_id'] ?? '')) : '';
                $clientSecret = is_array($cfg) ? trim((string) ($cfg['client_secret'] ?? '')) : '';
                if ($clientId !== '' && $clientSecret !== '') {
                    $configured = true;
                    break;
                }
            }
            if (! $configured) {
                continue;
            }
            $providers[$key] = [
                'label' => $meta['label'],
                'url' => $meta['url'],
            ];
        }

        return response()->json([
            'providers' => $providers,
            'redirect_scheme' => 'clicflow://oauth',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function userPayload(User $user): array
    {
        $company = $user->company;
        $roles = method_exists($user, 'getRoleNames')
            ? $user->getRoleNames()->values()->all()
            : [];

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'locale' => $user->locale ?? app()->getLocale(),
            'roles' => $roles,
            'company' => $company ? [
                'id' => $company->id,
                'name' => $company->name,
                'currency' => strtoupper((string) ($company->default_currency ?? flowdesk_default_currency())),
            ] : null,
        ];
    }

    /**
     * @return array{enabled: bool, unlimited: bool, used: int, limit: ?int, remaining: ?int, can_speak: bool}
     */
    private function aiCreditsPayload(?Company $company, ?PlanLimitService $planLimits = null): array
    {
        $planLimits ??= app(PlanLimitService::class);
        if (! $company) {
            return [
                'enabled' => false,
                'unlimited' => false,
                'used' => 0,
                'limit' => 0,
                'remaining' => 0,
                'can_speak' => false,
            ];
        }

        $nav = $planLimits->aiCreditsForNav($company);
        $enabled = $planLimits->isFeatureEnabled($company, 'ai_credits') && (bool) ($nav['show'] ?? false);
        $canSpeak = $enabled && (
            ! empty($nav['unlimited'])
            || (($nav['remaining'] ?? 0) > 0)
        );

        return [
            'enabled' => $enabled,
            'unlimited' => (bool) ($nav['unlimited'] ?? false),
            'used' => (int) ($nav['used'] ?? 0),
            'limit' => $nav['limit'] ?? null,
            'remaining' => $nav['remaining'] ?? null,
            'can_speak' => $canSpeak,
        ];
    }
}
