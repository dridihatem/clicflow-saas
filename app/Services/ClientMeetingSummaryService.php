<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Company;
use App\Models\WorkspaceCalendarEvent;
use RuntimeException;

class ClientMeetingSummaryService
{
    public function __construct(
        private PlanLimitService $planLimits,
        private AiCreditUsageService $usage,
        private PlatformLlmRouter $llm,
    ) {}

    /**
     * @return array{suggestion: string, model: string, ai_credits_charged: int, ai_credits_cost: int}
     */
    public function summarize(
        Company $company,
        Client $client,
        WorkspaceCalendarEvent $event,
        string $source,
        string $sourceLabel,
    ): array {
        $creditCost = $this->usage->creditsForTask(AiCreditUsageService::TASK_ASSISTANT, 'summary');
        $this->planLimits->assertAllows($company, 'ai_credits', $creditCost);

        if (! $this->llm->isAvailable($company)) {
            throw new RuntimeException(app(WorkspaceAiConfigService::class)->unavailableMessage($company));
        }

        $eventDescription = trim((string) ($event->description ?? ''));
        $system = 'You are an account manager assistant preparing an internal post-meeting summary for a client follow-up CRM. '
            .'Write a concise, professional summary with these sections only: Summary, Client feedback, Decisions, Next steps. '
            .'Use bullets where helpful. Do not invent facts. If details are missing, stay generic and mark assumptions briefly. '
            .AiAssistantPrompts::outputLanguageInstruction();

        $user = "Task: Turn the meeting material below into an internal follow-up summary after a Google Meet with a client.\n"
            .'Audience: company staff using the client detail page in ClicFlow.'."\n"
            .'Client: '.$client->name."\n"
            .'Meeting title: '.(string) $event->title."\n"
            .'Meeting date: '.$event->starts_on->toDateString()."\n"
            .'Existing meeting description: '.($eventDescription !== '' ? $eventDescription : 'None')."\n"
            .$sourceLabel.":\n".$source."\n\n"
            .'Return only the final summary text, ready to save into the meeting summary field.';

        $result = $this->llm->complete($system, $user, 1400, $company);
        $suggestion = trim((string) ($result['suggestion'] ?? ''));
        if ($suggestion === '') {
            throw new RuntimeException(__('Empty AI response.'));
        }

        $charged = $this->usage->recordForTask($company, AiCreditUsageService::TASK_ASSISTANT, 'summary');

        return [
            'suggestion' => $suggestion,
            'model' => (string) ($result['model'] ?? ''),
            'ai_credits_charged' => $charged,
            'ai_credits_cost' => $creditCost,
        ];
    }
}
