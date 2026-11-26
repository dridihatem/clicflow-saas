<?php

namespace App\Services;

use App\Models\DevicePushToken;
use App\Models\User;
use Google\Client as GoogleClient;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class FcmPushService
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{sent: int, failed: int}
     */
    public function sendToCompany(string $companyId, string $title, string $body, array $data = []): array
    {
        $tokens = DevicePushToken::query()
            ->where('company_id', $companyId)
            ->get();

        return $this->sendToTokens($tokens, $title, $body, $data);
    }

    /**
     * @param  iterable<int, User|string|int>  $users
     * @param  array<string, mixed>  $data
     * @return array{sent: int, failed: int}
     */
    public function sendToUsers(iterable $users, string $title, string $body, array $data = []): array
    {
        $ids = collect($users)->map(function ($user) {
            return $user instanceof User ? (string) $user->id : (string) $user;
        })->filter()->unique()->values()->all();

        if ($ids === []) {
            return ['sent' => 0, 'failed' => 0];
        }

        $tokens = DevicePushToken::query()->whereIn('user_id', $ids)->get();

        return $this->sendToTokens($tokens, $title, $body, $data);
    }

    /**
     * @param  Collection<int, DevicePushToken>  $tokens
     * @param  array<string, mixed>  $data
     * @return array{sent: int, failed: int}
     */
    public function sendToTokens(Collection $tokens, string $title, string $body, array $data = []): array
    {
        $sent = 0;
        $failed = 0;

        foreach ($tokens as $device) {
            try {
                $ok = $device->isExpo()
                    ? $this->sendExpo($device->token, $title, $body, $data)
                    : $this->sendFcm($device->token, $title, $body, $data);

                if ($ok) {
                    $device->forceFill(['last_used_at' => now()])->saveQuietly();
                    $sent++;
                } else {
                    $failed++;
                }
            } catch (Throwable $e) {
                $failed++;
                Log::warning('Push send failed', [
                    'token_id' => $device->id,
                    'provider' => $device->provider,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return ['sent' => $sent, 'failed' => $failed];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function sendExpo(string $token, string $title, string $body, array $data): bool
    {
        $payload = [
            'to' => $token,
            'title' => $title,
            'body' => $body,
            'sound' => 'default',
            'priority' => 'high',
            'data' => $this->stringifyData($data),
        ];

        $response = Http::acceptJson()
            ->asJson()
            ->timeout(15)
            ->post('https://exp.host/--/api/v2/push/send', $payload);

        if (! $response->successful()) {
            Log::warning('Expo push HTTP error', ['status' => $response->status(), 'body' => $response->body()]);

            return false;
        }

        $ticket = $response->json('data');
        $status = is_array($ticket) ? ($ticket['status'] ?? null) : null;

        if ($status === 'error') {
            $details = is_array($ticket) ? ($ticket['details']['error'] ?? null) : null;
            if (in_array($details, ['DeviceNotRegistered', 'InvalidCredentials'], true)) {
                DevicePushToken::query()->where('token', $token)->delete();
            }
            Log::warning('Expo push ticket error', ['ticket' => $ticket]);

            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function sendFcm(string $token, string $title, string $body, array $data): bool
    {
        $projectId = (string) config('services.fcm.project_id', '');
        $accessToken = $this->fcmAccessToken();

        if ($projectId === '' || $accessToken === null) {
            // Fall back to Expo if somehow mis-tagged; otherwise skip silently when FCM is not configured.
            if (str_starts_with($token, 'ExponentPushToken')) {
                return $this->sendExpo($token, $title, $body, $data);
            }

            Log::info('FCM not configured; skipping native token push');

            return false;
        }

        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->asJson()
            ->timeout(15)
            ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
                'message' => [
                    'token' => $token,
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                    ],
                    'data' => $this->stringifyData($data),
                    'android' => [
                        'priority' => 'HIGH',
                    ],
                    'apns' => [
                        'headers' => [
                            'apns-priority' => '10',
                        ],
                        'payload' => [
                            'aps' => [
                                'sound' => 'default',
                            ],
                        ],
                    ],
                ],
            ]);

        if ($response->successful()) {
            return true;
        }

        $errorCode = $response->json('error.details.0.errorCode')
            ?? $response->json('error.status');

        if (in_array($errorCode, ['UNREGISTERED', 'NOT_FOUND', 'INVALID_ARGUMENT'], true)) {
            DevicePushToken::query()->where('token', $token)->delete();
        }

        Log::warning('FCM push HTTP error', [
            'status' => $response->status(),
            'body' => $response->json() ?? $response->body(),
        ]);

        return false;
    }

    private function fcmAccessToken(): ?string
    {
        $credentials = config('services.fcm.credentials');
        if (! is_string($credentials) || $credentials === '' || ! is_file($credentials)) {
            return null;
        }

        try {
            $client = new GoogleClient;
            $client->setAuthConfig($credentials);
            $client->addScope('https://www.googleapis.com/auth/firebase.messaging');
            $token = $client->fetchAccessTokenWithAssertion();

            return is_array($token) ? ($token['access_token'] ?? null) : null;
        } catch (Throwable $e) {
            Log::warning('FCM access token failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    private function stringifyData(array $data): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            if ($value === null) {
                continue;
            }
            $out[(string) $key] = is_scalar($value) ? (string) $value : json_encode($value);
        }

        return $out;
    }
}
