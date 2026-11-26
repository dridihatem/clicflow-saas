<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\DevicePushToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DevicePushTokenController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:512'],
            'provider' => ['nullable', 'string', 'in:expo,fcm,apns'],
            'platform' => ['nullable', 'string', 'in:ios,android,web'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $user = $request->user();
        $token = trim($data['token']);
        $provider = $data['provider']
            ?? (str_starts_with($token, 'ExponentPushToken') ? 'expo' : 'fcm');

        $row = DevicePushToken::query()->updateOrCreate(
            ['token' => $token],
            [
                'user_id' => $user->id,
                'company_id' => $user->company_id,
                'provider' => $provider,
                'platform' => $data['platform'] ?? null,
                'device_name' => $data['device_name'] ?? null,
                'last_used_at' => now(),
            ],
        );

        return response()->json([
            'ok' => true,
            'id' => $row->id,
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:512'],
        ]);

        DevicePushToken::query()
            ->where('user_id', $request->user()->id)
            ->where('token', trim($data['token']))
            ->delete();

        return response()->json(['ok' => true]);
    }
}
