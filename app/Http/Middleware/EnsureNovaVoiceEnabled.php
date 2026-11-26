<?php

namespace App\Http\Middleware;

use App\Services\NovaVoiceNavigationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuses Nova voice endpoints (speech, wake-word intents, briefing, live voice)
 * when the company has switched the Nova voice assistant off.
 */
class EnsureNovaVoiceEnabled
{
    /**
     * $scope 'nova' (default): top-bar Nova assistant — company switch AND admin switch.
     * $scope 'chat': AI chat panel voice (the user's own dictation / read-aloud) — admin switch only,
     * so it keeps working when the company turned the top-bar Nova assistant off.
     */
    public function handle(Request $request, Closure $next, string $scope = 'nova'): Response
    {
        $company = $request->user()?->company;
        $allowed = $company === null || ($scope === 'chat'
            ? NovaVoiceNavigationService::adminVoiceEnabled($company)
            : NovaVoiceNavigationService::companyVoiceEnabled($company));
        if (! $allowed) {
            return response()->json([
                'ok' => false,
                'error' => 'nova_voice_disabled',
                'message' => __('nova_voice_disabled_message'),
            ], 403);
        }

        return $next($request);
    }
}
