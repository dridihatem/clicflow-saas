<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Services\NovaPublicDemoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class NovaPublicDemoController extends Controller
{
    public function status(NovaPublicDemoService $demo): JsonResponse
    {
        return response()->json($demo->statusPayload());
    }

    public function chat(Request $request, NovaPublicDemoService $demo): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
            'conversation_id' => ['nullable', 'string', 'max:40'],
        ]);

        try {
            $result = $demo->chat($data['message'], $data['conversation_id'] ?? null);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }

        return response()->json($result);
    }

    public function speak(Request $request, NovaPublicDemoService $demo): Response|JsonResponse|StreamedResponse
    {
        $data = $request->validate([
            'text' => ['required', 'string', 'max:4000'],
        ]);

        try {
            $result = $demo->speak($data['text']);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }

        return response($result['binary'], 200, [
            'Content-Type' => $result['mime'],
            'Cache-Control' => 'no-store, private',
            'X-Nova-TTS-Provider' => $result['provider'],
            'Access-Control-Expose-Headers' => 'X-Nova-TTS-Provider',
        ]);
    }

    public function liveSession(NovaPublicDemoService $demo): JsonResponse
    {
        try {
            $session = $demo->startLiveSession();
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'demo_live_unavailable',
            ], 503);
        }

        return response()->json($session);
    }

    public function liveTurn(Request $request, NovaPublicDemoService $demo): JsonResponse
    {
        $data = $request->validate([
            'audio_base64' => ['nullable', 'string', 'max:2500000'],
            'text' => ['nullable', 'string', 'max:1000'],
        ]);

        if (empty($data['audio_base64']) && empty($data['text'])) {
            return response()->json(['message' => __('No speech input received.')], 422);
        }

        try {
            $result = $demo->processLiveTurn($data['audio_base64'] ?? null, $data['text'] ?? null);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }

        return response()->json($result);
    }
}
