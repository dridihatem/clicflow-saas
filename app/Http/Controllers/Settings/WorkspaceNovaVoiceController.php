<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Settings\Concerns\AuthorizesWorkspaceManagers;
use App\Models\Company;
use App\Services\CartesiaTextToSpeechService;
use App\Services\WorkspaceAiConfigService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkspaceNovaVoiceController extends Controller
{
    use AuthorizesWorkspaceManagers;

    public function __construct(
        private WorkspaceAiConfigService $workspaceAi,
        private CartesiaTextToSpeechService $cartesia,
    ) {}

    public function edit(Request $request): View
    {
        $this->authorizeWorkspaceManagers($request);
        $company = $request->user()->company;
        abort_if(! $company instanceof Company, 403);

        $form = $this->workspaceAi->toFormArray($company);

        return view('settings.nova-voice', [
            'form' => $form,
            'company' => $company,
            'cartesiaAvailable' => $this->cartesia->isConfigured(),
            'catalog' => $this->cartesia->catalog(),
            'novaVoiceEnabled' => \App\Services\NovaVoiceNavigationService::companyPreferenceEnabled($company),
            'novaAdminDisabled' => ! \App\Services\NovaVoiceNavigationService::adminVoiceEnabled($company),
        ]);
    }

    /** Lightweight check polled by open pages (window focus) to stop or resume the mic. */
    public function status(Request $request): \Illuminate\Http\JsonResponse
    {
        $company = $request->user()?->company;

        return response()->json([
            'enabled' => \App\Services\NovaVoiceNavigationService::companyVoiceEnabled($company),
            'admin_enabled' => \App\Services\NovaVoiceNavigationService::adminVoiceEnabled($company),
        ]);
    }

    public function toggle(Request $request): RedirectResponse
    {
        $this->authorizeWorkspaceManagers($request);
        $company = $request->user()->company;
        abort_if(! $company instanceof Company, 403);

        $enabled = $request->boolean('nova_voice_enabled');
        \App\Models\CompanySetting::query()->withoutGlobalScopes()->updateOrCreate(
            ['company_id' => $company->id],
            ['nova_voice_enabled' => $enabled],
        );
        app(\App\Services\AuditLogService::class)->record(
            $enabled ? 'nova.voice_enabled' : 'nova.voice_disabled', null, [], (string) $company->id, $request->user()->id,
        );

        return redirect()
            ->route('settings.nova-voice')
            ->with('status', __($enabled ? 'nova_voice_enabled_saved' : 'nova_voice_disabled_saved'));
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorizeWorkspaceManagers($request);
        $company = $request->user()->company;
        abort_if(! $company instanceof Company, 403);

        $data = $request->validate([
            'cartesia_tts_voice' => ['nullable', 'string', 'max:64'],
            'cartesia_tts_voice_custom' => ['nullable', 'string', 'max:64'],
        ]);

        $custom = trim((string) ($data['cartesia_tts_voice_custom'] ?? ''));
        $preset = trim((string) ($data['cartesia_tts_voice'] ?? ''));
        $voice = $custom !== '' ? $custom : $preset;

        $this->workspaceAi->saveCartesiaVoice($company, $voice);

        return redirect()
            ->route('settings.nova-voice')
            ->with('status', __('workspace_nova_voice_saved'));
    }
}
