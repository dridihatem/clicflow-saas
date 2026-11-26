<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\AiAssistantPrompts;
use App\Services\AiCreditUsageService;
use App\Services\PlanLimitService;
use App\Services\PlatformLlmRouter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use JsonException;
use RuntimeException;
use Throwable;

class ProjectStudyAiController extends Controller
{
    public const TASK = 'project_study_draft';

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            abort_if(! $request->user()->hasAnyRole(['company_admin', 'team_member']), 403);
            abort_if(! $request->user()->can('workspace.manage_clients'), 403);

            return $next($request);
        });
    }

    public function draft(
        Request $request,
        PlatformLlmRouter $llm,
        PlanLimitService $planLimits,
        AiCreditUsageService $usage,
    ): JsonResponse {
        $company = $request->user()?->company;
        if (! $company) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $creditCost = $usage->creditsForTask(self::TASK);
        $planLimits->assertAllows($company, 'ai_credits', $creditCost);

        if (! $llm->isAvailable($company)) {
            return response()->json(['message' => __('ai_template_no_llm')], 422);
        }

        $data = $request->validate([
            'subject' => ['required', 'string', 'max:4000'],
            'title' => ['nullable', 'string', 'max:255'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'currency' => ['nullable', 'string', 'size:3'],
            'project_id' => [
                'nullable',
                'string',
                'max:26',
                Rule::exists('projects', 'id')->where(fn ($q) => $q->where('company_id', $company->id)),
            ],
        ]);

        $currency = flowdesk_normalize_currency_code($data['currency'] ?? $company->default_currency ?? flowdesk_default_currency());
        $subject = trim($data['subject']);
        $titleHint = trim((string) ($data['title'] ?? ''));
        $clientName = trim((string) ($data['client_name'] ?? ''));

        $projectContext = '';
        $priceHint = null;
        if (! empty($data['project_id'])) {
            $project = Project::query()
                ->withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->where('id', $data['project_id'])
                ->first(['id', 'title', 'description', 'final_price', 'negotiated_price']);

            if ($project) {
                $agreed = $project->clientAgreedPriceMinor();
                if ($agreed !== null) {
                    $priceHint = flowdesk_major_amount_for_input((int) $agreed, $currency);
                }
                $projectContext = "Linked project title: {$project->title}\n"
                    .'Linked project description: '.trim((string) ($project->description ?? ''))."\n"
                    .($priceHint !== null ? "Linked project agreed price (major units, {$currency}): {$priceHint}\n" : '');
            }
        }

        $system = 'You are a senior management consultant preparing a professional project study brief (étude de projet). '
            .'Reply with a single JSON object only (no markdown fences), keys: '
            .'"title" (short study title, max 120 chars), '
            .'"brief" (plain text professional template for the study brief — use clear numbered section headings, several sentences per section, no HTML/markdown #), '
            .'"budget" (number as string in major currency units, e.g. "25000.00", realistic estimate), '
            .'"budget_rationale" (one short sentence explaining the budget). '
            .'Brief MUST use this structure with these exact section titles translated to the user language: '
            .'1) Context & objectives, 2) Scope of work, 3) Technical approach, 4) Deliverables & timeline, '
            .'5) Budget assumptions, 6) Risks & constraints, 7) Success criteria. '
            .'Infer budget from the brief and any linked project description/price. Prefer the linked agreed price when present; otherwise estimate from scope. '
            .'Do not invent client legal names that were not provided. Keep practical and professional. '
            .AiAssistantPrompts::outputLanguageInstruction();

        $user = 'Company: '.((string) ($company->name ?? ''))."\n"
            ."Currency: {$currency}\n"
            .($clientName !== '' ? "Client: {$clientName}\n" : '')
            .($titleHint !== '' ? "Preferred title: {$titleHint}\n" : '')
            .$projectContext
            ."Voice / notes / subject for the project study:\n\n{$subject}";

        try {
            $out = $llm->complete($system, $user, 6000, $company);
        } catch (RuntimeException|Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $credits = $usage->recordForTask($company, self::TASK);
        $text = (string) ($out['suggestion'] ?? '');
        if ($text === '') {
            return response()->json(['message' => __('ai_template_empty')], 422);
        }

        $payload = $this->tryDecodeJsonObject($text);
        if (! is_array($payload)) {
            return response()->json(['message' => __('project_study_ai_invalid_json')], 422);
        }

        $title = isset($payload['title']) && is_string($payload['title']) ? trim($payload['title']) : '';
        $brief = isset($payload['brief']) && is_string($payload['brief']) ? trim($payload['brief']) : '';
        $budgetRaw = $payload['budget'] ?? null;
        $budget = is_numeric($budgetRaw) ? (string) $budgetRaw : (is_string($budgetRaw) ? trim($budgetRaw) : '');
        $budget = preg_replace('/[^\d.,\-]/', '', $budget) ?? '';
        $budget = str_replace(',', '.', $budget);
        if ($budget !== '' && ! is_numeric($budget)) {
            $budget = '';
        }
        $rationale = isset($payload['budget_rationale']) && is_string($payload['budget_rationale'])
            ? trim($payload['budget_rationale'])
            : '';

        if ($brief === '') {
            return response()->json(['message' => __('project_study_ai_missing_brief')], 422);
        }

        if ($title === '') {
            $title = mb_substr($subject, 0, 120);
        }

        return response()->json([
            'title' => mb_substr($title, 0, 255),
            'brief' => $brief,
            'budget' => $budget,
            'currency' => $currency,
            'budget_rationale' => $rationale,
            'ai_credits_charged' => $credits,
        ]);
    }

    private function tryDecodeJsonObject(string $raw): ?array
    {
        $s = trim($raw);
        if (preg_match('/```(?:json)?\s*(\{.*\})\s*```/s', $s, $m)) {
            $s = $m[1];
        }

        try {
            $d = json_decode($s, true, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $start = strpos($s, '{');
            $end = strrpos($s, '}');
            if ($start === false || $end === false || $end <= $start) {
                return null;
            }
            try {
                $d = json_decode(substr($s, $start, $end - $start + 1), true, 8, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                return null;
            }
        }

        return is_array($d) ? $d : null;
    }
}
