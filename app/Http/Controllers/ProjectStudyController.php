<?php

namespace App\Http\Controllers;

use App\Enums\ProposalStatus;
use App\Models\Client;
use App\Models\Project;
use App\Models\ProjectStudy;
use App\Models\Proposal;
use App\Models\ProposalItem;
use App\Services\AiCreditUsageService;
use App\Services\PlanLimitService;
use App\Services\PlatformLlmRouter;
use App\Services\InvoiceTotalsService;
use App\Services\ProposalReferenceService;
use App\Services\ProjectStudyAiService;
use App\Services\ProjectStudyPdfService;
use App\Services\ProjectStudyQuoteService;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class ProjectStudyController extends Controller
{
    public const AI_TASK = 'project_study';

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            abort_if(! $request->user()->hasAnyRole(['company_admin', 'team_member']), 403);
            abort_if(! $request->user()->can('workspace.manage_clients'), 403);

            return $next($request);
        });
    }

    public function index(Request $request): View
    {
        $company = $request->user()->company;
        abort_if(! $company, 403);

        $q = $request->string('q')->trim()->toString();
        $status = $request->string('status')->trim()->toString();
        $clientId = $request->string('client_id')->trim()->toString();

        $query = ProjectStudy::query()
            ->withoutGlobalScopes()
            ->with('client:id,name')
            ->where('company_id', $company->id)
            ->latest();

        if ($q !== '') {
            $query->where('title', 'like', '%'.$q.'%');
        }

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($clientId !== '') {
            $query->where('client_id', $clientId);
        }

        $studies = $query->paginate(20)->withQueryString();
        $clients = Client::query()
            ->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('project-studies.index', compact('studies', 'clients', 'q', 'status', 'clientId'));
    }

    public function create(Request $request, PlatformLlmRouter $llm, PlanLimitService $planLimits): View
    {
        $company = $request->user()->company;
        abort_if(! $company, 403);

        $clients = Client::query()
            ->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        $projects = Project::query()
            ->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->orderByDesc('created_at')
            ->limit(200)
            ->get(['id', 'title', 'description', 'final_price', 'negotiated_price', 'client_id']);

        $prefillClientId = $request->string('client_id')->toString();
        $currency = strtoupper((string) ($company->default_currency ?? flowdesk_default_currency()));
        $aiAvailable = $llm->isAvailable($company) && $planLimits->isFeatureEnabled($company, 'ai_credits');
        $aiCreditCost = app(AiCreditUsageService::class)->creditsForTask(ProjectStudyAiController::TASK);

        $projectOptions = $projects->map(function (Project $project) use ($currency) {
            $agreed = $project->clientAgreedPriceMinor();

            return [
                'id' => (string) $project->id,
                'title' => (string) $project->title,
                'description' => (string) ($project->description ?? ''),
                'client_id' => $project->client_id ? (string) $project->client_id : '',
                'budget' => $agreed !== null ? flowdesk_major_amount_for_input((int) $agreed, $currency) : '',
            ];
        })->values()->all();

        return view('project-studies.create', compact(
            'clients',
            'projects',
            'projectOptions',
            'prefillClientId',
            'currency',
            'aiAvailable',
            'aiCreditCost',
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $company = $request->user()->company;
        abort_if(! $company, 403);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'client_id' => [
                'nullable',
                'string',
                'max:26',
                Rule::exists('clients', 'id')->where(fn ($q) => $q->where('company_id', $company->id)),
            ],
            'project_id' => [
                'nullable',
                'string',
                'max:26',
                Rule::exists('projects', 'id')->where(fn ($q) => $q->where('company_id', $company->id)),
            ],
            'budget' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'brief' => ['nullable', 'string', 'max:10000'],
        ]);

        $currency = flowdesk_normalize_currency_code($data['currency'] ?? $company->default_currency ?? flowdesk_default_currency());
        $budgetMinor = isset($data['budget']) ? flowdesk_decimal_to_minor((string) $data['budget'], $currency) : null;

        $study = ProjectStudy::query()->create([
            'company_id' => $company->id,
            'client_id' => ($data['client_id'] ?? null) ?: null,
            'project_id' => ($data['project_id'] ?? null) ?: null,
            'title' => $data['title'],
            'status' => 'draft',
            'currency' => $currency,
            'budget_minor' => $budgetMinor,
            'executive_summary' => $data['brief'] ?? null,
        ]);

        return redirect()
            ->route('project-studies.show', $study)
            ->with('status', __('project_study_created'));
    }

    public function show(Request $request, ProjectStudy $projectStudy, PlatformLlmRouter $llm, PlanLimitService $planLimits): View
    {
        $company = $request->user()->company;
        abort_if(! $company || $projectStudy->company_id !== $company->id, 404);

        $projectStudy->load(['client:id,name', 'project:id,title']);
        $aiAvailable = $llm->isAvailable($company) && $planLimits->isFeatureEnabled($company, 'ai_credits');
        $creditCost = app(AiCreditUsageService::class)->creditsForTask(self::AI_TASK);

        return view('project-studies.show', [
            'study' => $projectStudy,
            'aiAvailable' => $aiAvailable,
            'creditCost' => $creditCost,
        ]);
    }

    public function edit(Request $request, ProjectStudy $projectStudy, PlatformLlmRouter $llm, PlanLimitService $planLimits): View
    {
        $company = $request->user()->company;
        abort_if(! $company || $projectStudy->company_id !== $company->id, 404);

        $clients = Client::query()
            ->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        $projects = Project::query()
            ->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->orderByDesc('created_at')
            ->limit(200)
            ->get(['id', 'title']);

        $aiAvailable = $llm->isAvailable($company) && $planLimits->isFeatureEnabled($company, 'ai_credits');
        $creditCost = app(AiCreditUsageService::class)->creditsForTask(self::AI_TASK);

        return view('project-studies.edit', [
            'study' => $projectStudy,
            'clients' => $clients,
            'projects' => $projects,
            'aiAvailable' => $aiAvailable,
            'creditCost' => $creditCost,
        ]);
    }

    public function update(Request $request, ProjectStudy $projectStudy): RedirectResponse
    {
        $company = $request->user()->company;
        abort_if(! $company || $projectStudy->company_id !== $company->id, 404);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'client_id' => [
                'nullable',
                'string',
                'max:26',
                Rule::exists('clients', 'id')->where(fn ($q) => $q->where('company_id', $company->id)),
            ],
            'project_id' => [
                'nullable',
                'string',
                'max:26',
                Rule::exists('projects', 'id')->where(fn ($q) => $q->where('company_id', $company->id)),
            ],
            'status' => ['nullable', 'string', Rule::in(['draft', 'generated', 'final'])],
            'budget' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'executive_summary' => ['nullable', 'string', 'max:100000'],
            'technical_study' => ['nullable', 'string', 'max:100000'],
            'financial_study' => ['nullable', 'string', 'max:100000'],
            'methodology' => ['nullable', 'string', 'max:100000'],
            'risks' => ['nullable', 'string', 'max:100000'],
            'conclusion' => ['nullable', 'string', 'max:100000'],
        ]);

        $currency = flowdesk_normalize_currency_code($data['currency'] ?? $projectStudy->currency ?? $company->default_currency ?? flowdesk_default_currency());
        $budgetMinor = array_key_exists('budget', $data) && $data['budget'] !== null && $data['budget'] !== ''
            ? flowdesk_decimal_to_minor((string) $data['budget'], $currency)
            : $projectStudy->budget_minor;

        $projectStudy->update([
            'title' => $data['title'],
            'client_id' => ($data['client_id'] ?? null) ?: null,
            'project_id' => ($data['project_id'] ?? null) ?: null,
            'status' => $data['status'] ?? $projectStudy->status,
            'currency' => $currency,
            'budget_minor' => $budgetMinor,
            'executive_summary' => $data['executive_summary'] ?? null,
            'technical_study' => $data['technical_study'] ?? null,
            'financial_study' => $data['financial_study'] ?? null,
            'methodology' => $data['methodology'] ?? null,
            'risks' => $data['risks'] ?? null,
            'conclusion' => $data['conclusion'] ?? null,
        ]);

        return redirect()
            ->route('project-studies.show', $projectStudy)
            ->with('status', __('project_study_saved'));
    }

    public function destroy(Request $request, ProjectStudy $projectStudy): RedirectResponse
    {
        $company = $request->user()->company;
        abort_if(! $company || $projectStudy->company_id !== $company->id, 404);

        $projectStudy->delete();

        return redirect()
            ->route('project-studies.index')
            ->with('status', __('project_study_deleted'));
    }

    public function generateAi(
        Request $request,
        ProjectStudy $projectStudy,
        ProjectStudyAiService $ai,
        PlanLimitService $planLimits,
        AiCreditUsageService $usage,
    ): RedirectResponse {
        $company = $request->user()->company;
        abort_if(! $company || $projectStudy->company_id !== $company->id, 404);

        $creditCost = $usage->creditsForTask(self::AI_TASK);
        $planLimits->assertAllows($company, 'ai_credits', $creditCost);

        try {
            $generated = $ai->generate($company, $projectStudy);
        } catch (RuntimeException $e) {
            return redirect()
                ->route('project-studies.show', $projectStudy)
                ->with('error', $e->getMessage());
        }

        $projectStudy->update([
            'executive_summary' => $generated['executive_summary'],
            'technical_study' => $generated['technical_study'],
            'financial_study' => $generated['financial_study'],
            'methodology' => $generated['methodology'],
            'risks' => $generated['risks'],
            'conclusion' => $generated['conclusion'],
            'full_body' => $generated['full_body'],
            'status' => 'generated',
            'generated_at' => now(),
        ]);

        $usage->recordForTask($company, self::AI_TASK);

        return redirect()
            ->route('project-studies.show', $projectStudy)
            ->with('status', __('project_study_generated'));
    }

    public function storeProposalFromStudy(
        Request $request,
        ProjectStudy $projectStudy,
        ProjectStudyQuoteService $studyQuotes,
        InvoiceTotalsService $totals,
        ProposalReferenceService $proposalRefs,
    ): RedirectResponse {
        $company = $request->user()->company;
        abort_if(! $company || $projectStudy->company_id !== $company->id, 404);

        if (! $projectStudy->client_id) {
            return back()->with('error', __('project_study_quote_needs_client'));
        }

        $data = $request->validate([
            'mode' => ['sometimes', 'string', Rule::in(['ai', 'manual'])],
        ]);
        $useAi = ($data['mode'] ?? 'ai') !== 'manual';

        $projectStudy->load('client:id,name', 'project:id,title,description,final_price,negotiated_price');
        $currency = flowdesk_normalize_currency_code($projectStudy->currency ?? $company->default_currency ?? flowdesk_default_currency());
        $brief = $studyQuotes->studyBriefForQuote($projectStudy);
        $quoteName = $studyQuotes->quoteName($projectStudy);

        try {
            $items = $studyQuotes->buildLineItems($projectStudy, $company, $useAi);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $subtotal = 0;
        foreach ($items as $item) {
            $subtotal += ((int) $item['quantity']) * ((int) $item['unit_amount']);
        }
        $taxes = $totals->fromSubtotalMinor($subtotal, $company);

        $proposal = DB::transaction(function () use ($company, $projectStudy, $currency, $items, $taxes, $brief, $quoteName, $proposalRefs) {
            $proposal = Proposal::query()->withoutGlobalScopes()->create([
                'company_id' => $company->id,
                'client_id' => $projectStudy->client_id,
                'project_id' => $projectStudy->project_id,
                'name' => $quoteName,
                'number' => null,
                'status' => ProposalStatus::Draft,
                'subtotal_amount' => $taxes['subtotal'],
                'vat_amount' => $taxes['vat'],
                'fiscal_stamp_amount' => $taxes['stamp'],
                'amount' => $taxes['total'],
                'currency' => $currency,
                'customer_notes' => mb_substr($brief, 0, 10000),
            ]);

            foreach ($items as $item) {
                $qty = (int) $item['quantity'];
                $unit = (int) $item['unit_amount'];
                ProposalItem::query()->withoutGlobalScopes()->create([
                    'company_id' => $company->id,
                    'proposal_id' => $proposal->id,
                    'product_id' => $item['product_id'] ?? null,
                    // AI lines carry their own title; manual study lines stay as before.
                    'title' => flowdesk_line_title($item['title'] ?? null),
                    'description' => mb_substr((string) ($item['description'] ?? ''), 0, 2000),
                    'unit' => $item['unit'] ?? null,
                    'quantity' => $qty,
                    'unit_amount' => $unit,
                    'total_amount' => $qty * $unit,
                ]);
            }

            $proposalRefs->assignNextNumber($proposal, $company);

            return $proposal;
        });

        return redirect()
            ->route('proposals.edit', $proposal)
            ->with('status', __('project_study_quote_created'));
    }

    public function pdf(Request $request, ProjectStudy $projectStudy, ProjectStudyPdfService $pdf): Response
    {
        $company = $request->user()->company;
        abort_if(! $company || $projectStudy->company_id !== $company->id, 404);

        return $pdf->download($projectStudy);
    }
}
