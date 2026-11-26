<?php

namespace App\Http\Controllers\Commerce;

use App\Http\Controllers\Controller;
use App\Services\AiCreditUsageService;
use App\Services\PlanLimitService;
use App\Services\PlatformLlmRouter;
use App\Services\ProductCatalogAiService;
use App\Services\ProductCopyAiService;
use App\Services\WorkspaceAiConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

class ProductAiController extends Controller
{
    public const TASK = 'product_copy';

    public const CATALOG_TASK = 'product_catalog';

    public function suggestCopy(
        Request $request,
        PlanLimitService $planLimits,
        AiCreditUsageService $usage,
        PlatformLlmRouter $llm,
        ProductCopyAiService $copyAi,
    ): JsonResponse {
        $company = $request->user()?->company;
        abort_if(! $company, 403);
        abort_if(
            ! $request->user()->can('workspace.manage_commerce')
            && ! $request->user()->can('workspace.manage_invoices'),
            403,
        );

        $data = $request->validate([
            'brief' => ['required', 'string', 'min:8', 'max:4000'],
            'kind' => ['required', 'string', Rule::in(['product', 'service'])],
            'name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:8000'],
        ]);

        $creditCost = $usage->creditsForTask(self::TASK);
        $planLimits->assertAllows($company, 'ai_credits', $creditCost);

        if (! $llm->isAvailable($company)) {
            return response()->json([
                'message' => app(WorkspaceAiConfigService::class)->unavailableMessage($company),
            ], 503);
        }

        try {
            $copy = $copyAi->generate(
                $data['brief'],
                $data['kind'],
                $data['name'] ?? null,
                $data['description'] ?? null,
                $company,
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $charged = $usage->recordForTask($company, self::TASK);

        return response()->json([
            'name' => $copy['name'],
            'description' => $copy['description'],
            'unit' => $copy['unit'],
            'disclaimer' => __('AI-generated content — review before sending to clients.'),
            'ai_credits_charged' => $charged,
            'ai_credits_cost' => $creditCost,
        ]);
    }

    public function generateCatalog(
        Request $request,
        PlanLimitService $planLimits,
        AiCreditUsageService $usage,
        PlatformLlmRouter $llm,
        ProductCatalogAiService $catalogAi,
    ): RedirectResponse {
        $company = $request->user()?->company;
        abort_if(! $company, 403);
        abort_if(
            ! $request->user()->can('workspace.manage_commerce')
            && ! $request->user()->can('workspace.manage_invoices'),
            403,
        );

        $data = $request->validate([
            'brief' => ['required', 'string', 'min:8', 'max:4000'],
        ]);

        $creditCost = $usage->creditsForTask(self::CATALOG_TASK);
        $planLimits->assertAllows($company, 'ai_credits', $creditCost);

        if (! $llm->isAvailable($company)) {
            return redirect()
                ->route('commerce.products.index')
                ->withErrors(['brief' => app(WorkspaceAiConfigService::class)->unavailableMessage($company)]);
        }

        try {
            $created = $catalogAi->generateAndStore($data['brief'], $company);
        } catch (RuntimeException $e) {
            return redirect()
                ->route('commerce.products.index')
                ->withInput()
                ->withErrors(['brief' => $e->getMessage()]);
        }

        $usage->recordForTask($company, self::CATALOG_TASK);

        $names = collect($created)->pluck('name')->filter()->implode(', ');

        return redirect()
            ->route('commerce.products.index')
            ->with('status', __('catalog_ai_bulk_created', [
                'count' => count($created),
                'names' => $names,
            ]));
    }
}
