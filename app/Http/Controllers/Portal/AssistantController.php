<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Concerns\ResolvesPortalClient;
use App\Models\ClientProspect;
use App\Services\PortalAiAdvisorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AssistantController extends Controller
{
    use ResolvesPortalClient;

    public function __construct(private PortalAiAdvisorService $advisor) {}

    public function index(Request $request): View
    {
        $client = $this->portalClient($request);
        $user = $request->user();
        $company = $user->company;
        abort_if(! $company, 403);

        $available = $this->advisor->isAvailable($company);
        $conversations = $this->advisor->conversations($company, $user);
        $conversationId = $request->string('conversation')->toString();
        $conversation = null;
        if ($conversationId !== '') {
            try {
                $conversation = $this->advisor->loadConversation($company, $user, $conversationId);
            } catch (\Throwable) {
                $conversation = null;
            }
        }

        $prefill = $request->string('prompt')->toString();
        $prospectId = $request->string('prospect')->toString();
        $prospect = null;
        if ($prospectId !== '') {
            $prospect = ClientProspect::query()
                ->where('client_id', $client->id)
                ->where('id', $prospectId)
                ->first();
            if ($prospect && $prefill === '') {
                $prefill = $this->advisor->suggestedPromptForProspect($prospect);
            }
        }

        return view('portal.assistant.index', [
            'client' => $client,
            'available' => $available,
            'unavailableMessage' => $available ? null : $this->advisor->unavailableMessage($company),
            'conversations' => $conversations,
            'conversation' => $conversation,
            'prefill' => $prefill,
            'prospect' => $prospect,
        ]);
    }

    public function chat(Request $request): JsonResponse
    {
        $client = $this->portalClient($request);
        $user = $request->user();
        $company = $user->company;
        abort_if(! $company, 403);

        $data = $request->validate([
            'message' => ['required', 'string', 'max:4000'],
            'conversation_id' => ['nullable', 'string', 'max:26'],
            'prospect_id' => ['nullable', 'string', 'max:26'],
        ]);

        try {
            $result = $this->advisor->chat(
                $company,
                $user,
                $client,
                $data['message'],
                $data['conversation_id'] ?? null,
                $data['prospect_id'] ?? null,
            );
        } catch (HttpException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getStatusCode());
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($result);
    }
}
