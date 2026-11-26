<?php

namespace App\Http\Controllers;

use App\Services\AiSidebar\AiChatService;
use App\Services\PlanLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiChatPanelController extends Controller
{
    public function __construct(private AiChatService $chat, private PlanLimitService $planLimits) {}

    private function guard(Request $request): array
    {
        $user = $request->user();
        $company = $user?->company;
        abort_if(! $company, 403);
        abort_unless($user->hasAnyRole(['company_admin', 'team_member']), 403);
        abort_unless($this->planLimits->isFeatureEnabled($company, 'ai_credits'), 403, __('ai_sidebar_plan_required'));

        return [$user, $company];
    }

    public function show(Request $request): JsonResponse
    {
        [$user, $company] = $this->guard($request);
        $conversation = $this->chat->currentConversation($company, $user);

        return response()->json([
            'conversation_id' => $conversation?->id,
            'messages' => $this->chat->history($conversation),
        ]);
    }

    public function reset(Request $request): JsonResponse
    {
        [$user, $company] = $this->guard($request);
        $current = $this->chat->currentConversation($company, $user);
        $conversation = ($current && $current->messages()->doesntExist()) ? $current : $this->chat->startConversation($company, $user);

        return response()->json(['conversation_id' => $conversation->id, 'messages' => []]);
    }

    public function send(Request $request): JsonResponse
    {
        [$user, $company] = $this->guard($request);
        $data = $request->validate([
            'message' => ['required', 'string', 'min:2', 'max:2000'],
            'conversation_id' => ['nullable', 'string', 'max:40'],
            'context' => ['nullable', 'array'],
            'context.form' => ['nullable', 'string', 'in:quote,invoice,client,product,project,task'],
            'context.fields' => ['nullable', 'array', 'max:20'],
            'context.fields.*' => ['nullable', 'string', 'max:2000'],
            'context.discount' => ['nullable', 'array'],
            'context.discount.type' => ['nullable', 'string', 'max:10'],
            'context.discount.value' => ['nullable', 'string', 'max:24'],
            'context.currency' => ['nullable', 'string', 'max:8'],
            'context.lines' => ['nullable', 'array', 'max:40'],
            'context.lines.*.description' => ['nullable', 'string', 'max:2000'],
            'context.lines.*.quantity' => ['nullable', 'numeric'],
            'context.lines.*.unit_minor' => ['nullable', 'numeric'],
            'context.lines.*.product_id' => ['nullable', 'string', 'max:40'],
        ]);

        return response()->json($this->chat->send($company, $user, $data['message'], $data['conversation_id'] ?? null, $data['context'] ?? []));
    }
}
