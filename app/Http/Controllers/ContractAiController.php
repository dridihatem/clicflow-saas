<?php

namespace App\Http\Controllers;

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

class ContractAiController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            abort_if(! $request->user()->hasAnyRole(['company_admin', 'team_member']), 403);
            abort_if(! $request->user()->can('workspace.manage_clients'), 403);

            return $next($request);
        });
    }

    public function generate(
        Request $request,
        PlatformLlmRouter $llm,
        PlanLimitService $planLimits,
        AiCreditUsageService $usage,
    ): JsonResponse {
        $company = $request->user()?->company;
        if (! $company) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $creditCost = $usage->creditsForTask(AiCreditUsageService::TASK_CONTRACT_BODY);
        $planLimits->assertAllows($company, 'ai_credits', $creditCost);

        if (! $llm->isAvailable($company)) {
            return response()->json(['message' => __('ai_template_no_llm')], 422);
        }

        $data = $request->validate([
            'subject' => ['required', 'string', 'max:2000'],
            'type_key' => ['nullable', 'string', Rule::in(['service', 'nda', 'retainer', 'consulting', 'custom'])],
            'client_name' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
        ]);

        $typeKey = $data['type_key'] ?? 'service';
        $typeLabel = __('contract_type_'.$typeKey);
        $companyName = (string) ($company->name ?? '');
        $clientName = trim((string) ($data['client_name'] ?? ''));
        $titleHint = trim((string) ($data['title'] ?? ''));
        $subject = trim($data['subject']);

        $mergeTags = '{{client_name}}, {{client_email}}, {{client_phone}}, {{company_name}}, {{company_address}}, '
            .'{{contract_title}}, {{contract_reference}}, {{starts_on}}, {{ends_on}}, {{today}}';

        $system = 'You are an expert business contract drafting assistant. Reply with a single JSON object only (no markdown fences), keys: '
            .'"title" (short contract title, max 120 chars), '
            .'"body_html" (professional contract body as HTML fragment — not a full HTML document). '
            .'HTML rules: allowed tags only <h2>, <h3>, <p>, <ul>, <ol>, <li>, <strong>, <em>, <br>, <hr>, <table>, <thead>, <tbody>, <tr>, <th>, <td>. '
            .'No scripts, styles, iframes, or event handlers. '
            .'Structure: parties intro, definitions if useful, scope/services or obligations, term/duration, fees/payment if relevant, '
            .'confidentiality, liability limitations (reasonable), termination, governing law (generic placeholder), signature block note. '
            .'Use merge tags where party/date details belong: '.$mergeTags.'. '
            .'Do not invent specific laws, court names, or real addresses. Keep professional and practical. '
            .AiAssistantPrompts::outputLanguageInstruction();

        $user = "Company (provider): {$companyName}\n"
            .'Contract type: '.$typeLabel." ({$typeKey})\n"
            .($clientName !== '' ? "Client name hint: {$clientName}\n" : '')
            .($titleHint !== '' ? "Preferred title: {$titleHint}\n" : '')
            ."Subject / brief for the contract:\n\n{$subject}";

        try {
            $out = $llm->complete($system, $user, 8000, $company);
        } catch (RuntimeException|Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $credits = $usage->recordForTask($company, AiCreditUsageService::TASK_CONTRACT_BODY);

        $text = (string) ($out['suggestion'] ?? '');
        if ($text === '') {
            return response()->json(['message' => __('ai_template_empty')], 422);
        }

        $payload = $this->tryDecodeJsonObject($text);
        if (! is_array($payload)) {
            return response()->json(['message' => __('contract_ai_invalid_json')], 422);
        }

        $title = isset($payload['title']) && is_string($payload['title']) ? trim($payload['title']) : '';
        $body = isset($payload['body_html']) && is_string($payload['body_html']) ? trim($payload['body_html']) : '';
        if ($body === '') {
            return response()->json(['message' => __('contract_ai_missing_body')], 422);
        }

        if ($title === '') {
            $title = mb_substr($subject, 0, 120);
        }

        return response()->json([
            'title' => mb_substr($title, 0, 255),
            'body_html' => $body,
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
