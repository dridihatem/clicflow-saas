<?php

namespace App\Http\Controllers\Api\Company;

use App\Enums\InvoiceStatus;
use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Mail\ClientMessageMail;
use App\Models\ChatThread;
use App\Models\Client;
use App\Models\ClientContract;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\Proposal;
use App\Models\User;
use App\Models\WorkspaceCalendarEvent;
use App\Services\ClientDeletionService;
use App\Services\PlatformLlmRouter;
use App\Services\PlanLimitService;
use App\Services\TenantSmtpMailer;
use App\Services\WorkspaceInsightsSummaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use RuntimeException;

class WorkspaceController extends Controller
{
    public function dashboard(
        Request $request,
        PlanLimitService $planLimits,
        WorkspaceInsightsSummaryService $insightsAi,
    ): JsonResponse {
        $company = $request->user()->company;
        abort_if(! $company, 403);

        $cid = $company->id;
        $currency = flowdesk_normalize_currency_code(
            $request->user()->default_currency
            ?? $company->default_currency
            ?? null
        );

        $clientsTotal = Client::query()
            ->withoutGlobalScopes()
            ->where('company_id', $cid)
            ->whereNull('deleted_at')
            ->count();
        $clientsWithProjects = (int) Project::query()
            ->withoutGlobalScopes()
            ->where('company_id', $cid)
            ->whereNotNull('client_id')
            ->distinct()
            ->count('client_id');

        $projectsBase = Project::query()->withoutGlobalScopes()->where('company_id', $cid);
        $projectsTotal = (clone $projectsBase)->count();
        $projectsInProgress = (clone $projectsBase)->where('status', ProjectStatus::InProgress->value)->count();
        $projectsCompleted = (clone $projectsBase)->where('status', ProjectStatus::Completed->value)->count();
        $projectsPending = (clone $projectsBase)->whereIn('status', [
            ProjectStatus::Pending->value,
            ProjectStatus::Approved->value,
            ProjectStatus::Draft->value,
        ])->count();

        $invoicesBase = Invoice::query()->withoutGlobalScopes()->where('company_id', $cid);
        $invoicesTotal = (clone $invoicesBase)->count();
        $openInvoicesQuery = (clone $invoicesBase)->whereNotIn('status', [
            InvoiceStatus::Paid->value,
            InvoiceStatus::Cancelled->value,
            'void',
        ]);
        $openInvoices = (clone $openInvoicesQuery)->count();
        $openAmountMinor = (int) (clone $openInvoicesQuery)->sum('amount');
        $paidAmountMinor = (int) (clone $invoicesBase)->where('status', InvoiceStatus::Paid->value)->sum('amount');
        $proposalsTotal = Proposal::query()->withoutGlobalScopes()->where('company_id', $cid)->count();

        $format = function (int $minor) use ($currency): string {
            return function_exists('flowdesk_format_minor')
                ? flowdesk_format_minor($minor, $currency).' '.$currency
                : ((string) $minor).' '.$currency;
        };

        $summaries = [
            'clients' => [
                'total' => $clientsTotal,
                'with_projects' => $clientsWithProjects,
                'without_projects' => max(0, $clientsTotal - $clientsWithProjects),
            ],
            'financial' => [
                'currency' => $currency,
                'invoices' => $invoicesTotal,
                'open_invoices' => $openInvoices,
                'open_amount' => $format($openAmountMinor),
                'open_amount_minor' => $openAmountMinor,
                'paid_amount' => $format($paidAmountMinor),
                'paid_amount_minor' => $paidAmountMinor,
                'quotes' => $proposalsTotal,
            ],
            'projects' => [
                'total' => $projectsTotal,
                'in_progress' => $projectsInProgress,
                'completed' => $projectsCompleted,
                'pending' => $projectsPending,
            ],
        ];

        $aiCredits = $planLimits->aiCreditsForNav($company);
        $canUseAi = $planLimits->isFeatureEnabled($company, 'ai_credits')
            && (
                ! empty($aiCredits['unlimited'])
                || (($aiCredits['remaining'] ?? 0) > 0)
            );

        $aiNarratives = [
            'available' => false,
            'generated' => false,
            'charged' => 0,
            'clients' => null,
            'financial' => null,
            'projects' => null,
            'reason' => $canUseAi ? null : 'ai_credits_limit',
        ];

        if ($canUseAi) {
            $aiNarratives = $insightsAi->summarize($company, $summaries);
        }

        return response()->json([
            'stats' => [
                'clients' => $clientsTotal,
                'projects' => $projectsTotal,
                'proposals' => $proposalsTotal,
                'invoices' => $invoicesTotal,
                'open_invoices' => $openInvoices,
            ],
            'summaries' => $summaries,
            'ai_summaries' => $aiNarratives,
            'ai_credits' => [
                'enabled' => (bool) ($aiCredits['show'] ?? false) && $planLimits->isFeatureEnabled($company, 'ai_credits'),
                'unlimited' => (bool) ($aiCredits['unlimited'] ?? false),
                'used' => (int) ($aiCredits['used'] ?? 0),
                'limit' => $aiCredits['limit'] ?? null,
                'remaining' => $aiCredits['remaining'] ?? null,
                'can_speak' => $canUseAi,
            ],
        ]);
    }

    public function clients(Request $request): JsonResponse
    {
        $company = $request->user()->company;
        abort_if(! $company, 403);

        $q = trim((string) $request->query('q', ''));
        $query = Client::query()
            ->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->whereNull('deleted_at')
            ->latest();

        if ($q !== '') {
            $query->where(function ($builder) use ($q) {
                $builder->where('name', 'like', '%'.$q.'%')
                    ->orWhere('email', 'like', '%'.$q.'%')
                    ->orWhere('phone', 'like', '%'.$q.'%')
                    ->orWhere('code', 'like', '%'.$q.'%');
            });
        }

        $page = $query->paginate(30);
        $page->setCollection(
            $page->getCollection()->map(fn (Client $client) => $this->clientSummary($client, $company->id))
        );

        return response()->json($page);
    }

    public function client(Request $request, string $client): JsonResponse
    {
        $company = $request->user()->company;
        abort_if(! $company, 403);

        $model = $this->findClient($company->id, $client);
        $payload = $this->clientSummary($model, $company->id, withInvoices: true);

        return response()->json(['client' => $payload]);
    }

    public function updateClient(Request $request, string $client): JsonResponse
    {
        $company = $request->user()->company;
        abort_if(! $company, 403);
        abort_if(! $request->user()->can('workspace.manage_clients'), 403);

        $model = $this->findClient($company->id, $client);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
        ]);

        $model->forceFill([
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
        ])->save();

        return response()->json([
            'client' => $this->clientSummary($model->refresh(), $company->id, withInvoices: true),
            'message' => __('Client updated.'),
        ]);
    }

    public function destroyClient(Request $request, string $client, ClientDeletionService $deletion): JsonResponse
    {
        $company = $request->user()->company;
        abort_if(! $company, 403);
        abort_if(! $request->user()->can('workspace.manage_clients'), 403);

        $model = $this->findClient($company->id, $client);

        try {
            $deletion->delete($model);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'message' => __('Client deleted.')]);
    }

    public function draftClientEmail(Request $request, string $client, PlatformLlmRouter $llm): JsonResponse
    {
        $company = $request->user()->company;
        abort_if(! $company, 403);
        $model = $this->findClient($company->id, $client);

        $data = $request->validate([
            'text' => ['required', 'string', 'max:8000'],
            'subject' => ['nullable', 'string', 'max:200'],
        ]);

        $raw = trim($data['text']);
        $subjectHint = trim((string) ($data['subject'] ?? ''));

        if (! $llm->isAvailable($company)) {
            $subject = $subjectHint !== '' ? $subjectHint : __('Message from :company', ['company' => $company->name]);

            return response()->json([
                'subject' => $subject,
                'body' => $raw,
                'draft_token' => $this->storeEmailDraft($request->user()->id, $model->id, $subject, $raw),
                'confirm_prompt' => __('Say “Nova confirm” or tap Confirm to send this email.'),
                'ai' => false,
            ]);
        }

        $system = 'You polish rough notes into a professional client email. '
            .'Reply with exactly two parts: first line "Subject: ...", then a blank line, then the email body only. '
            .'No greetings about yourself. Keep the sender intent. Locale: '.app()->getLocale().'.';

        $userPrompt = "Client name: {$model->name}\nCompany: {$company->name}\n"
            .($subjectHint !== '' ? "Preferred subject hint: {$subjectHint}\n" : '')
            ."Rough notes / spoken draft:\n{$raw}";

        try {
            $result = $llm->complete($system, $userPrompt, 900, $company);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }

        [$subject, $body] = $this->parseEmailDraft((string) ($result['suggestion'] ?? ''), $subjectHint, $raw);
        $token = $this->storeEmailDraft($request->user()->id, $model->id, $subject, $body);

        return response()->json([
            'subject' => $subject,
            'body' => $body,
            'draft_token' => $token,
            'confirm_prompt' => __('Say “Nova confirm” or tap Confirm to send this email.'),
            'ai' => true,
            'model' => (string) ($result['model'] ?? 'llm'),
        ]);
    }

    public function sendClientEmail(Request $request, string $client, TenantSmtpMailer $mailer): JsonResponse
    {
        $company = $request->user()->company;
        abort_if(! $company, 403);
        $model = $this->findClient($company->id, $client);

        $data = $request->validate([
            'confirm' => ['required', 'accepted'],
            'draft_token' => ['nullable', 'string', 'max:40'],
            'subject' => ['nullable', 'string', 'max:200'],
            'body' => ['nullable', 'string', 'max:8000'],
        ]);

        $subject = trim((string) ($data['subject'] ?? ''));
        $body = trim((string) ($data['body'] ?? ''));

        if (! empty($data['draft_token'])) {
            $cached = Cache::pull($this->emailDraftKey($request->user()->id, (string) $data['draft_token']));
            if (is_array($cached) && (string) ($cached['client_id'] ?? '') === (string) $model->id) {
                $subject = trim((string) ($cached['subject'] ?? $subject));
                $body = trim((string) ($cached['body'] ?? $body));
            }
        }

        if ($subject === '') {
            $subject = __('Message from :company', ['company' => $company->name]);
        }
        if ($body === '') {
            return response()->json(['message' => __('Email body is empty.')], 422);
        }

        $to = trim((string) ($model->email ?? ''));
        if ($to === '' || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return response()->json(['message' => __('Client has no email address.')], 422);
        }

        try {
            $mailer->send(
                $company,
                new ClientMessageMail($company, $model, $subject, $body, (string) $request->user()->name),
                $to,
            );
        } catch (RuntimeException|\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }

        return response()->json([
            'ok' => true,
            'message' => __('Email sent to :email', ['email' => $to]),
            'to' => $to,
        ]);
    }

    public function projects(Request $request): JsonResponse
    {
        $company = $request->user()->company;
        abort_if(! $company, 403);

        $q = trim((string) $request->query('q', ''));
        $status = $request->query('status');

        // Keep SoftDeletes so trashed projects never appear (mobile detail would 404).
        $query = Project::query()
            ->withoutGlobalScope('tenant')
            ->where('company_id', $company->id)
            ->with(['client:id,name', 'provider:id,name'])
            ->withCount([
                'tasks',
                'tasks as done_tasks_count' => fn ($builder) => $builder->where('status', TaskStatus::Done->value),
            ])
            ->latest();

        if ($q !== '') {
            $query->where(function ($builder) use ($q) {
                $builder->where('title', 'like', '%'.$q.'%')
                    ->orWhereHas('client', fn ($c) => $c->where('name', 'like', '%'.$q.'%'));
            });
        }

        if (is_string($status) && $status !== '' && in_array($status, array_column(ProjectStatus::cases(), 'value'), true)) {
            $query->where('status', $status);
        }

        return response()->json($query->paginate(20));
    }

    public function destroyProject(Request $request, string $project): JsonResponse
    {
        $company = $request->user()->company;
        abort_if(! $company, 403);
        abort_if(! $request->user()->can('workspace.manage_projects'), 403);

        $model = Project::query()
            ->withoutGlobalScope('tenant')
            ->where('company_id', $company->id)
            ->whereKey($project)
            ->firstOrFail();

        ProjectTask::query()
            ->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('project_id', $model->id)
            ->delete();

        $model->delete();

        return response()->json(['ok' => true, 'message' => __('Project deleted.')]);
    }

    public function project(Request $request, string $project): JsonResponse
    {
        $company = $request->user()->company;
        abort_if(! $company, 403);

        $model = Project::query()
            ->withoutGlobalScope('tenant')
            ->where('company_id', $company->id)
            ->whereKey($project)
            ->firstOrFail();

        $model->load([
            'client:id,name',
            'provider:id,name',
            'teamMembers:id,name,email',
            'tasks' => fn ($q) => $q->orderBy('sort_order')->limit(100),
        ]);

        $doneCount = $model->tasks->filter(function ($t) {
            $status = $t->status;

            return $status === TaskStatus::Done
                || $status === TaskStatus::Done->value
                || (is_object($status) && ($status->value ?? null) === TaskStatus::Done->value);
        })->count();
        $totalTasks = $model->tasks->count();

        $team = User::query()
            ->where('company_id', $company->id)
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'assigned' => $model->teamMembers->contains('id', $u->id),
            ])
            ->values()
            ->all();

        $status = $model->status instanceof \BackedEnum
            ? $model->status->value
            : (string) ($model->status ?? '');

        return response()->json([
            'project' => [
                'id' => $model->id,
                'title' => $model->title,
                'status' => $status,
                'client' => $model->client ? [
                    'id' => $model->client->id,
                    'name' => $model->client->name,
                ] : null,
                'provider' => $model->provider ? [
                    'id' => $model->provider->id,
                    'name' => $model->provider->name,
                ] : null,
                'team_members' => $model->teamMembers->map(fn (User $u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                ])->values()->all(),
                'tasks' => $model->tasks->map(function ($task) {
                    $taskStatus = $task->status instanceof \BackedEnum
                        ? $task->status->value
                        : (string) ($task->status ?? '');

                    return [
                        'id' => $task->id,
                        'title' => $task->title,
                        'description' => $task->description,
                        'status' => $taskStatus,
                        'starts_on' => optional($task->starts_on)?->toDateString(),
                        'ends_on' => optional($task->ends_on)?->toDateString(),
                    ];
                })->values()->all(),
            ],
            'progress_pct' => $totalTasks > 0 ? (int) round(($doneCount / $totalTasks) * 100) : 0,
            'done_tasks_count' => $doneCount,
            'total_tasks' => $totalTasks,
            'company_team' => $team,
            'task_statuses' => array_map(fn (TaskStatus $s) => $s->value, TaskStatus::cases()),
        ]);
    }

    public function updateTask(Request $request, string $project, string $task): JsonResponse
    {
        $company = $request->user()->company;
        abort_if(! $company, 403);

        $projectModel = Project::query()
            ->withoutGlobalScope('tenant')
            ->where('company_id', $company->id)
            ->whereKey($project)
            ->firstOrFail();

        $taskModel = ProjectTask::query()
            ->withoutGlobalScope('tenant')
            ->where('company_id', $company->id)
            ->where('project_id', $projectModel->id)
            ->whereKey($task)
            ->firstOrFail();

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['sometimes', Rule::enum(TaskStatus::class)],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
        ]);

        $taskModel->fill($data)->save();
        $taskModel->refresh();

        $status = $taskModel->status instanceof \BackedEnum
            ? $taskModel->status->value
            : (string) ($taskModel->status ?? '');

        return response()->json([
            'task' => [
                'id' => $taskModel->id,
                'title' => $taskModel->title,
                'description' => $taskModel->description,
                'status' => $status,
                'starts_on' => optional($taskModel->starts_on)?->toDateString(),
                'ends_on' => optional($taskModel->ends_on)?->toDateString(),
            ],
            'message' => __('Task updated.'),
        ]);
    }

    public function updateProjectTeam(Request $request, string $project): JsonResponse
    {
        $company = $request->user()->company;
        abort_if(! $company, 403);

        $projectModel = Project::query()
            ->withoutGlobalScope('tenant')
            ->where('company_id', $company->id)
            ->whereKey($project)
            ->firstOrFail();

        $data = $request->validate([
            'user_ids' => ['present', 'array'],
            'user_ids.*' => [
                Rule::exists('users', 'id')->where(fn ($q) => $q->where('company_id', $company->id)),
            ],
        ]);

        $ids = array_map(fn ($id) => (string) $id, $data['user_ids']);
        $projectModel->teamMembers()->sync($ids);
        $projectModel->load('teamMembers:id,name,email');

        return response()->json([
            'team_members' => $projectModel->teamMembers->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
            ])->values()->all(),
            'message' => __('Team updated.'),
        ]);
    }

    public function proposal(Request $request, string $proposal): JsonResponse
    {
        $company = $request->user()->company;
        abort_if(! $company, 403);

        $model = Proposal::query()
            ->withoutGlobalScope('tenant')
            ->where('company_id', $company->id)
            ->whereKey($proposal)
            ->with(['client:id,name,email', 'items'])
            ->firstOrFail();

        $currency = (string) ($model->currency ?? '');
        $amount = function_exists('flowdesk_format_minor')
            ? flowdesk_format_minor((int) $model->amount, $currency).' '.$currency
            : ((string) $model->amount).' '.$currency;
        $status = $model->status instanceof \BackedEnum
            ? $model->status->value
            : (string) ($model->status ?? '');

        return response()->json([
            'proposal' => [
                'id' => $model->id,
                'reference' => $model->number ?: $model->id,
                'name' => $model->name,
                'amount' => $amount,
                'currency' => $currency,
                'status' => $status,
                'valid_until' => optional($model->valid_until)?->toDateString(),
                'customer_notes' => $model->customer_notes,
                'client' => $model->client ? [
                    'id' => $model->client->id,
                    'name' => $model->client->name,
                    'email' => $model->client->email,
                ] : null,
                'items' => $model->items->map(fn ($item) => [
                    'id' => $item->id,
                    'title' => $item->title,
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit_amount' => $item->unit_amount,
                    'total_amount' => $item->total_amount,
                ])->values()->all(),
            ],
        ]);
    }

    public function invoice(Request $request, string $invoice): JsonResponse
    {
        $company = $request->user()->company;
        abort_if(! $company, 403);

        $model = Invoice::query()
            ->withoutGlobalScope('tenant')
            ->where('company_id', $company->id)
            ->whereKey($invoice)
            ->with(['client:id,name,email', 'items'])
            ->firstOrFail();

        $currency = function_exists('flowdesk_invoice_currency')
            ? flowdesk_invoice_currency($model)
            : (string) ($model->currency ?? '');
        $amount = function_exists('flowdesk_format_minor')
            ? flowdesk_format_minor((int) $model->amount, $currency).' '.$currency
            : ((string) $model->amount).' '.$currency;
        $status = $model->status instanceof \BackedEnum
            ? $model->status->value
            : (string) ($model->status ?? '');

        return response()->json([
            'invoice' => [
                'id' => $model->id,
                'reference' => $model->number ?: $model->id,
                'amount' => $amount,
                'currency' => $currency,
                'status' => $status,
                'due_date' => optional($model->due_date)?->toDateString(),
                'customer_notes' => $model->customer_notes,
                'settlement' => $model->paymentSettlementKey(),
                'client' => $model->client ? [
                    'id' => $model->client->id,
                    'name' => $model->client->name,
                    'email' => $model->client->email,
                ] : null,
                'items' => $model->items->map(fn ($item) => [
                    'id' => $item->id,
                    'title' => $item->title,
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit_amount' => $item->unit_amount,
                    'total_amount' => $item->total_amount,
                ])->values()->all(),
            ],
        ]);
    }

    public function proposals(Request $request): JsonResponse
    {
        $company = $request->user()->company;
        abort_if(! $company, 403);

        $proposals = Proposal::query()
            ->withoutGlobalScope('tenant')
            ->where('company_id', $company->id)
            ->with(['client:id,name'])
            ->latest()
            ->paginate(20);

        return response()->json($proposals);
    }

    public function invoices(Request $request): JsonResponse
    {
        $company = $request->user()->company;
        abort_if(! $company, 403);

        $invoices = Invoice::query()
            ->withoutGlobalScope('tenant')
            ->where('company_id', $company->id)
            ->with(['client:id,name'])
            ->latest()
            ->paginate(20);

        return response()->json($invoices);
    }

    public function messages(Request $request): JsonResponse
    {
        $company = $request->user()->company;
        $user = $request->user();
        abort_if(! $company, 403);

        $threads = ChatThread::query()
            ->where('company_id', $company->id)
            ->whereHas('participants', fn ($p) => $p->where('users.id', $user->id))
            ->with(['messages' => fn ($m) => $m->latest()->limit(1)])
            ->latest('updated_at')
            ->limit(40)
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'type' => $t->type,
                'name' => $t->resolveDisplayNameFor($user),
                'updated_at' => optional($t->updated_at)?->toIso8601String(),
                'last_message' => optional($t->messages->first())->body,
            ]);

        return response()->json(['threads' => $threads]);
    }

    public function notifications(Request $request): JsonResponse
    {
        $user = $request->user();

        $notifications = $user->notifications()->latest()->limit(40)->get()->map(fn ($n) => [
            'id' => $n->id,
            'type' => class_basename($n->type),
            'data' => $n->data,
            'read_at' => optional($n->read_at)?->toIso8601String(),
            'created_at' => optional($n->created_at)?->toIso8601String(),
        ]);

        return response()->json(['notifications' => $notifications]);
    }

    private function findClient(string $companyId, string $clientId): Client
    {
        return Client::query()
            ->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->whereKey($clientId)
            ->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function clientSummary(Client $client, string $companyId, bool $withInvoices = false): array
    {
        $status = $client->status instanceof \BackedEnum
            ? $client->status->value
            : (string) ($client->status ?? '');

        $base = Invoice::query()
            ->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('client_id', $client->id);

        $invoiceCount = (clone $base)->count();
        $paymentDueCount = (clone $base)->settlement('overdue')->count();
        $pendingPaymentCount = (clone $base)
            ->settlement('unpaid')
            ->where(function ($q) {
                $q->whereNull('due_date')
                    ->orWhereDate('due_date', '>=', now()->toDateString());
            })
            ->count()
            + (clone $base)->settlement('partial')->count();

        $payload = [
            'id' => $client->id,
            'code' => $client->code,
            'name' => $client->name,
            'email' => $client->email,
            'phone' => $client->phone,
            'status' => $status,
            'address' => $client->address,
            'invoice_count' => $invoiceCount,
            'can_delete' => $invoiceCount === 0,
            'payment_due_count' => $paymentDueCount,
            'pending_payment_count' => $pendingPaymentCount,
        ];

        if ($withInvoices) {
            $openInvoices = (clone $base)
                ->whereNotIn('status', [
                    InvoiceStatus::Paid->value,
                    InvoiceStatus::Cancelled->value,
                    'void',
                ])
                ->latest()
                ->limit(40)
                ->get();

            $paymentDue = [];
            $pendingPayment = [];

            foreach ($openInvoices as $invoice) {
                $settlement = $invoice->paymentSettlementKey();
                $row = $this->invoiceRow($invoice, $settlement);

                if ($settlement === 'overdue') {
                    $paymentDue[] = $row;
                } elseif (in_array($settlement, ['unpaid', 'partial'], true)) {
                    $pendingPayment[] = $row;
                }
            }

            $payload['payment_due'] = $paymentDue;
            $payload['pending_payment'] = $pendingPayment;
            $payload['payment_due_count'] = count($paymentDue);
            $payload['pending_payment_count'] = count($pendingPayment);

            $payload['invoices'] = Invoice::query()
                ->withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('client_id', $client->id)
                ->latest()
                ->limit(40)
                ->get()
                ->map(fn (Invoice $invoice) => $this->invoiceRow($invoice, $invoice->paymentSettlementKey()))
                ->values()
                ->all();

            $payload['proposals'] = Proposal::query()
                ->withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('client_id', $client->id)
                ->latest()
                ->limit(40)
                ->get()
                ->map(function (Proposal $proposal) {
                    $currency = (string) ($proposal->currency ?? '');
                    $amount = function_exists('flowdesk_format_minor')
                        ? flowdesk_format_minor((int) $proposal->amount, $currency).' '.$currency
                        : ((string) $proposal->amount).' '.$currency;
                    $status = $proposal->status instanceof \BackedEnum
                        ? $proposal->status->value
                        : (string) ($proposal->status ?? '');

                    return [
                        'id' => $proposal->id,
                        'reference' => $proposal->number ?: $proposal->id,
                        'name' => $proposal->name,
                        'amount' => $amount,
                        'valid_until' => optional($proposal->valid_until)?->toDateString(),
                        'status' => $status,
                    ];
                })
                ->values()
                ->all();

            $payload['contracts'] = ClientContract::query()
                ->withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('client_id', $client->id)
                ->latest()
                ->limit(40)
                ->get()
                ->map(function (ClientContract $contract) {
                    $status = $contract->status instanceof \BackedEnum
                        ? $contract->status->value
                        : (string) ($contract->status ?? '');

                    return [
                        'id' => $contract->id,
                        'title' => $contract->title,
                        'reference' => $contract->reference,
                        'status' => $status,
                        'starts_on' => optional($contract->starts_on)?->toDateString(),
                        'ends_on' => optional($contract->ends_on)?->toDateString(),
                    ];
                })
                ->values()
                ->all();

            $calendarEvents = WorkspaceCalendarEvent::query()
                ->withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('client_id', $client->id)
                ->whereIn('kind', ['meeting', 'reminder', 'appointment'])
                ->orderByDesc('starts_on')
                ->limit(50)
                ->get();

            $payload['meetings'] = $calendarEvents
                ->filter(fn (WorkspaceCalendarEvent $e) => in_array((string) $e->kind, ['meeting', 'appointment'], true))
                ->values()
                ->map(fn (WorkspaceCalendarEvent $e) => $this->calendarEventRow($e))
                ->all();

            $payload['reminders'] = $calendarEvents
                ->filter(fn (WorkspaceCalendarEvent $e) => (string) $e->kind === 'reminder')
                ->values()
                ->map(fn (WorkspaceCalendarEvent $e) => $this->calendarEventRow($e))
                ->all();
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function calendarEventRow(WorkspaceCalendarEvent $event): array
    {
        return [
            'id' => $event->id,
            'title' => $event->title,
            'description' => $event->description,
            'date' => optional($event->starts_on)?->toDateString(),
            'time' => $event->start_time,
            'kind' => (string) $event->kind,
            'meeting_url' => $event->meeting_url ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function invoiceRow(Invoice $invoice, string $settlement): array
    {
        $currency = function_exists('flowdesk_invoice_currency')
            ? flowdesk_invoice_currency($invoice)
            : (string) ($invoice->currency ?? '');
        $amount = function_exists('flowdesk_format_minor')
            ? flowdesk_format_minor((int) $invoice->amount, $currency).' '.$currency
            : ((string) $invoice->amount).' '.$currency;

        $status = $invoice->status instanceof \BackedEnum
            ? $invoice->status->value
            : (string) ($invoice->status ?? '');

        return [
            'id' => $invoice->id,
            'reference' => $invoice->number ?: $invoice->id,
            'amount' => $amount,
            'due_date' => optional($invoice->due_date)?->toDateString(),
            'status' => $status,
            'settlement' => $settlement,
            'kind' => $settlement === 'overdue' ? 'payment_due' : 'pending_payment',
        ];
    }

    private function storeEmailDraft(string $userId, string $clientId, string $subject, string $body): string
    {
        $token = (string) Str::ulid();
        Cache::put($this->emailDraftKey($userId, $token), [
            'client_id' => $clientId,
            'subject' => $subject,
            'body' => $body,
        ], now()->addMinutes(30));

        return $token;
    }

    private function emailDraftKey(string $userId, string $token): string
    {
        return 'company_client_email_draft:'.$userId.':'.$token;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function parseEmailDraft(string $suggestion, string $fallbackSubject, string $fallbackBody): array
    {
        $text = trim($suggestion);
        if ($text === '') {
            return [
                $fallbackSubject !== '' ? $fallbackSubject : __('Message'),
                $fallbackBody,
            ];
        }

        $subject = $fallbackSubject;
        $body = $text;

        if (preg_match('/^\s*Subject\s*:\s*(.+)$/im', $text, $m)) {
            $subject = trim($m[1]);
            $body = trim((string) preg_replace('/^\s*Subject\s*:.*\R+/im', '', $text, 1));
        }

        if ($subject === '') {
            $subject = __('Message');
        }
        if ($body === '') {
            $body = $fallbackBody;
        }

        return [$subject, $body];
    }
}
