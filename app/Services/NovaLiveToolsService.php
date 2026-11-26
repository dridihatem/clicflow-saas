<?php

namespace App\Services;

use App\Models\User;

class NovaLiveToolsService
{
    public function __construct(
        private NovaVoiceNavigationService $navigation,
        private NovaVoiceWorkflowService $workflows,
        private NovaClientAnalysisService $clientAnalysis,
        private NovaIdentityService $identity,
        private PlanLimitService $planLimits,
    ) {}

    /**
     * Gemini functionDeclarations for Live / turn-based STS.
     *
     * @return list<array<string, mixed>>
     */
    public function functionDeclarations(User $user): array
    {
        $gates = $this->planLimits->featureGates($user->company);
        $commands = $this->navigation->commandsFor($user, $gates);
        $workflows = $this->workflows->workflowsFor($user, $gates);

        $commandIds = array_values(array_map(fn (array $c) => (string) $c['id'], $commands));
        $workflowIds = array_values(array_map(fn (array $w) => (string) $w['id'], $workflows));

        $decls = [
            [
                'name' => 'navigate',
                'description' => 'Open a workspace page by command id (dashboard, clients, invoices, settings, etc.).',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'command_id' => [
                            'type' => 'STRING',
                            'description' => 'One of: '.implode(', ', array_slice($commandIds, 0, 40)),
                        ],
                    ],
                    'required' => ['command_id'],
                ],
            ],
            [
                'name' => 'who_are_you',
                'description' => 'Answer when the user asks who Nova is or what Nova can do.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'detail' => [
                            'type' => 'BOOLEAN',
                            'description' => 'True for a longer introduction.',
                        ],
                    ],
                ],
            ],
            [
                'name' => 'analyze_clients',
                'description' => 'Analyze all clients or one client by name (meetings, invoices, quotes, risks).',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'client_name' => [
                            'type' => 'STRING',
                            'description' => 'Optional client name. Empty means analyze all clients.',
                        ],
                    ],
                ],
            ],
            [
                'name' => 'ask_workspace',
                'description' => 'Ask a workspace business question that needs Nova chat knowledge (metrics, advice). Prefer navigate/workflows for concrete actions.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'question' => [
                            'type' => 'STRING',
                            'description' => 'The user question in their language.',
                        ],
                    ],
                    'required' => ['question'],
                ],
            ],
        ];

        if ($workflowIds !== []) {
            $decls[] = [
                'name' => 'start_workflow',
                'description' => 'Start a guided voice workflow to create records or update settings.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'workflow_id' => [
                            'type' => 'STRING',
                            'description' => 'One of: '.implode(', ', $workflowIds),
                        ],
                    ],
                    'required' => ['workflow_id'],
                ],
            ];
        }

        return $decls;
    }

    public function systemHints(User $user): string
    {
        $gates = $this->planLimits->featureGates($user->company);
        $workflows = $this->workflows->workflowsFor($user, $gates);
        $lines = [];

        foreach ($workflows as $workflow) {
            $lines[] = '- '.$workflow['id'].': '.$workflow['label'];
        }

        $list = $lines !== [] ? implode("\n", $lines) : '- (none available)';

        return "Available workflows:\n{$list}\nUse start_workflow for create client, HR employee, provider, VAT, or workspace language.";
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array{ok: bool, message: string, action?: string, url?: string, workflow_id?: string, reply?: string}
     */
    public function execute(User $user, string $name, array $args = []): array
    {
        return match ($name) {
            'navigate' => $this->executeNavigate($user, (string) ($args['command_id'] ?? '')),
            'start_workflow' => $this->executeStartWorkflow($user, (string) ($args['workflow_id'] ?? '')),
            'who_are_you' => [
                'ok' => true,
                'message' => $this->identity->reply($user->company, $user),
                'action' => 'speak',
                'reply' => $this->identity->reply($user->company, $user),
            ],
            'analyze_clients' => $this->executeAnalyze($user, isset($args['client_name']) ? (string) $args['client_name'] : null),
            'ask_workspace' => [
                'ok' => true,
                'message' => 'Forward question to Nova chat.',
                'action' => 'ask_chat',
                'reply' => (string) ($args['question'] ?? ''),
            ],
            default => [
                'ok' => false,
                'message' => 'Unknown tool: '.$name,
            ],
        };
    }

    /**
     * @return array{ok: bool, message: string, action?: string, url?: string}
     */
    private function executeNavigate(User $user, string $commandId): array
    {
        $commandId = trim($commandId);
        if ($commandId === '') {
            return ['ok' => false, 'message' => 'Missing command_id'];
        }

        $gates = $this->planLimits->featureGates($user->company);
        foreach ($this->navigation->commandsFor($user, $gates) as $command) {
            if ((string) $command['id'] !== $commandId) {
                continue;
            }

            if (($command['action'] ?? null) === 'logout') {
                return [
                    'ok' => true,
                    'message' => 'Logging out.',
                    'action' => 'logout',
                    'url' => (string) ($command['url'] ?? route('logout')),
                ];
            }

            $url = (string) ($command['url'] ?? '');
            if ($url === '') {
                return ['ok' => false, 'message' => 'Command has no URL'];
            }

            return [
                'ok' => true,
                'message' => 'Opening '.$command['label'],
                'action' => 'navigate',
                'url' => $url,
            ];
        }

        return ['ok' => false, 'message' => 'Unknown command_id: '.$commandId];
    }

    /**
     * @return array{ok: bool, message: string, action?: string, workflow_id?: string, reply?: string}
     */
    private function executeStartWorkflow(User $user, string $workflowId): array
    {
        $workflowId = trim($workflowId);
        if ($workflowId === '') {
            return ['ok' => false, 'message' => 'Missing workflow_id'];
        }

        try {
            $result = $this->workflows->start($user, $workflowId);
        } catch (\InvalidArgumentException $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }

        return [
            'ok' => true,
            'message' => (string) ($result['reply'] ?? 'Workflow started'),
            'action' => 'start_workflow',
            'workflow_id' => $workflowId,
            'reply' => (string) ($result['reply'] ?? ''),
        ];
    }

    /**
     * @return array{ok: bool, message: string, action?: string, reply?: string}
     */
    private function executeAnalyze(User $user, ?string $clientName): array
    {
        $company = $user->company;
        if (! $company) {
            return ['ok' => false, 'message' => 'No company'];
        }

        if ($clientName && trim($clientName) !== '') {
            $message = 'Analyze client '.trim($clientName);
            $reply = $this->clientAnalysis->tryReply($company, $user, $message)
                ?? $this->clientAnalysis->analyzeClientsOverview($company);
        } else {
            $reply = $this->clientAnalysis->analyzeClientsOverview($company);
        }

        return [
            'ok' => true,
            'message' => 'Analysis ready',
            'action' => 'speak',
            'reply' => $reply,
        ];
    }
}
