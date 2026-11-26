<?php

namespace App\Http\Controllers;

use App\Services\AiCreditUsageService;
use App\Services\AiSidebar\AiDraftService;
use App\Services\PlanLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AiSidebarDraftController extends Controller
{
    public function store(Request $request, AiDraftService $drafts, PlanLimitService $planLimits, AiCreditUsageService $usage): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        $company = $user?->company;
        abort_if(! $company, 403);
        abort_unless($user->hasAnyRole(['company_admin', 'team_member']), 403);
        abort_unless($planLimits->isFeatureEnabled($company, 'ai_credits'), 403, __('ai_sidebar_plan_required'));

        $data = $request->validate([
            'prompt' => ['required', 'string', 'min:6', 'max:2000'],
        ]);

        $useLlm = $drafts->llmAvailable($company);
        $cost = $usage->creditsForTask('ai_sidebar_draft');
        if ($useLlm) {
            $planLimits->assertAllows($company, 'ai_credits', $cost);
        }

        $result = $drafts->createDraft($company, $user, $data['prompt'], $useLlm);
        if ($result['draft']['source'] === 'ai') {
            $usage->recordForTask($company, 'ai_sidebar_draft');
        }

        $url = $drafts->draftUrl($result['draft'], $result['token']);

        if ($request->expectsJson()) {
            return response()->json([
                'redirect' => $url,
                'entity' => $result['draft']['entity'],
                'source' => $result['draft']['source'],
                'unresolved' => count($result['draft']['unresolved']),
            ]);
        }

        return redirect()->to($url);
    }
}
