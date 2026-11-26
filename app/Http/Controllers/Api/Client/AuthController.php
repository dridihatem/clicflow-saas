<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        /** @var User|null $user */
        $user = User::query()->where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        if (! $user->hasRole('client') || ! $user->clientProfile) {
            throw ValidationException::withMessages([
                'email' => [__('These credentials are not authorized for the client app.')],
            ]);
        }

        if (method_exists($user, 'hasTwoFactorEnabled') && $user->hasTwoFactorEnabled()) {
            return response()->json([
                'message' => __('Two-factor authentication is enabled. Sign in on the web portal to continue.'),
                'requires_2fa' => true,
            ], 403);
        }

        $token = $user->createToken($data['device_name'] ?? 'mobile')->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->userPayload($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $this->userPayload($request->user()),
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
     * Start URLs for the same web Socialite providers (GitHub / LinkedIn).
     * Mobile opens these in an auth session; callback returns clicflow://oauth?token=…
     */
    public function oauthProviders(): JsonResponse
    {
        return response()->json([
            'providers' => [
                'github' => [
                    'label' => 'GitHub',
                    'url' => url('/auth/github/redirect?client=mobile'),
                ],
                'linkedin' => [
                    'label' => 'LinkedIn',
                    'url' => url('/auth/linkedin/redirect?client=mobile'),
                ],
            ],
            'redirect_scheme' => 'clicflow://oauth',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function userPayload(User $user): array
    {
        $client = $user->clientProfile;
        $company = $user->company;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'locale' => $user->locale ?? app()->getLocale(),
            'client' => $client ? [
                'id' => $client->id,
                'name' => $client->name,
                'email' => $client->email,
                'code' => $client->code,
            ] : null,
            'company' => $company ? [
                'id' => $company->id,
                'name' => $company->name,
                'currency' => strtoupper((string) ($company->default_currency ?? flowdesk_default_currency())),
            ] : null,
        ];
    }
}
