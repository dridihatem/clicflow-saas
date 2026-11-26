<?php

namespace App\Http\Controllers;

use App\Services\AiCreditUsageService;
use App\Services\CompanyGrowthAiService;
use App\Services\PlanLimitService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GrowthAdvisorController extends Controller
{
    public function __invoke(
        Request $request,
        PlanLimitService $planLimits,
        CompanyGrowthAiService $growthAi,
        AiCreditUsageService $aiCredits,
    ): View {
        $company = $request->user()->company;
        abort_unless($company !== null, 403);

        $aiGrowthModules = collect($growthAi->modules())->map(function (array $module) use ($growthAi, $company, $aiCredits) {
            $creditCost = $aiCredits->creditsForTask(AiCreditUsageService::TASK_ASSISTANT, $module['mode']);

            return array_merge($module, [
                'context' => $growthAi->buildContext($company, $module['mode']),
                'credit_cost' => $creditCost,
            ]);
        })->all();

        $minGrowthCost = collect($aiGrowthModules)->min('credit_cost') ?: 100;
        $aiGrowthAvailable = $planLimits->isFeatureEnabled($company, 'ai_credits')
            && $planLimits->allows($company, 'ai_credits', (int) $minGrowthCost);

        return view('growth-advisor.index', compact('aiGrowthAvailable', 'aiGrowthModules'));
    }
}
