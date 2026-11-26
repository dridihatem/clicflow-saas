<?php

namespace App\Http\Controllers;

use App\Enums\ClientContractStatus;
use App\Mail\ContractSentMail;
use App\Models\Client;
use App\Models\ClientContract;
use App\Models\ContractTemplate;
use App\Services\ContractMergeService;
use App\Services\ContractPdfService;
use App\Services\ContractReferenceService;
use App\Services\ContractSampleService;
use App\Services\ContractTemplateBootstrapService;
use App\Services\PlanLimitService;
use App\Services\PlatformLlmRouter;
use App\Services\TenantSmtpMailer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContractController extends Controller
{
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
        $type = $request->string('type')->trim()->toString();
        $clientId = $request->string('client_id')->trim()->toString();

        $query = ClientContract::query()
            ->withoutGlobalScopes()
            ->with('client')
            ->where('company_id', $company->id)
            ->latest();

        if ($q !== '') {
            $query->where(function ($builder) use ($q): void {
                $builder->where('title', 'like', '%'.$q.'%')
                    ->orWhere('reference', 'like', '%'.$q.'%');
            });
        }

        if ($status !== '' && in_array($status, ClientContractStatus::values(), true)) {
            $query->where('status', $status);
        }

        if ($type !== '') {
            $query->where('type_key', $type);
        }

        if ($clientId !== '') {
            $query->where('client_id', $clientId);
        }

        $contracts = $query->paginate(20)->withQueryString();
        $clients = Client::query()
            ->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('contracts.index', compact('contracts', 'clients', 'q', 'status', 'type', 'clientId'));
    }

    public function create(
        Request $request,
        ContractTemplateBootstrapService $bootstrap,
        PlatformLlmRouter $llm,
        PlanLimitService $planLimits,
    ): View {
        $company = $request->user()->company;
        abort_if(! $company, 403);

        $bootstrap->ensureDefaults($company);

        $clients = Client::query()
            ->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        $templates = ContractTemplate::query()
            ->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $prefillClientId = $request->string('client_id')->toString();
        $aiAvailable = $llm->isAvailable($company) && $planLimits->isFeatureEnabled($company, 'ai_credits');

        return view('contracts.create', compact('clients', 'templates', 'prefillClientId', 'aiAvailable'));
    }

    public function store(
        Request $request,
        ContractReferenceService $references,
        ContractTemplateBootstrapService $bootstrap,
    ): RedirectResponse {
        $company = $request->user()->company;
        abort_if(! $company, 403);

        $bootstrap->ensureDefaults($company);

        $data = $request->validate([
            'client_id' => [
                'required',
                'string',
                Rule::exists('clients', 'id')->where(fn ($q) => $q->where('company_id', $company->id)),
            ],
            'contract_template_id' => [
                'nullable',
                'string',
                Rule::exists('contract_templates', 'id')->where(fn ($q) => $q->where('company_id', $company->id)),
            ],
            'title' => ['nullable', 'string', 'max:255'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'body_html' => ['nullable', 'string', 'max:2000000'],
        ]);

        $template = null;
        if (! empty($data['contract_template_id'])) {
            $template = ContractTemplate::query()
                ->withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->whereKey($data['contract_template_id'])
                ->firstOrFail();
        }

        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            $title = $template?->name ?? __('Contract');
        }

        $body = (string) ($data['body_html'] ?? '');
        if ($body === '' && $template) {
            $body = (string) $template->body_html;
        }
        abort_if($body === '', 422, __('contract_body_required'));

        $contract = ClientContract::query()->create([
            'company_id' => $company->id,
            'client_id' => $data['client_id'],
            'contract_template_id' => $template?->id,
            'title' => $title,
            'type_key' => $template?->type_key ?? 'custom',
            'body_html' => $body,
            'status' => ClientContractStatus::Draft,
            'reference' => $references->next($company),
            'starts_on' => $data['starts_on'] ?? null,
            'ends_on' => $data['ends_on'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        return redirect()
            ->route('contracts.show', $contract)
            ->with('status', __('contract_saved'));
    }

    public function show(Request $request, ClientContract $contract, ContractMergeService $merge): View
    {
        $this->authorizeContract($request, $contract);
        $contract->load(['client', 'template', 'company']);

        $previewHtml = $merge->interpolate((string) $contract->body_html, $contract);

        return view('contracts.show', compact('contract', 'previewHtml'));
    }

    public function edit(
        Request $request,
        ClientContract $contract,
        ContractMergeService $merge,
        PlatformLlmRouter $llm,
        PlanLimitService $planLimits,
    ): View {
        $this->authorizeContract($request, $contract);
        abort_unless($contract->isEditable(), 403);

        $company = $request->user()->company;
        $clients = Client::query()
            ->withoutGlobalScopes()
            ->where('company_id', $request->user()->company_id)
            ->orderBy('name')
            ->get(['id', 'name']);

        $aiAvailable = $company
            && $llm->isAvailable($company)
            && $planLimits->isFeatureEnabled($company, 'ai_credits');

        return view('contracts.edit', [
            'contract' => $contract,
            'clients' => $clients,
            'mergeHints' => $merge->editorHints(),
            'aiAvailable' => $aiAvailable,
        ]);
    }

    public function update(Request $request, ClientContract $contract): RedirectResponse
    {
        $this->authorizeContract($request, $contract);
        abort_unless($contract->isEditable(), 403);
        $company = $request->user()->company;
        abort_if(! $company, 403);

        $data = $request->validate([
            'client_id' => [
                'required',
                'string',
                Rule::exists('clients', 'id')->where(fn ($q) => $q->where('company_id', $company->id)),
            ],
            'title' => ['required', 'string', 'max:255'],
            'type_key' => ['required', 'string', 'max:40', Rule::in(['service', 'nda', 'retainer', 'consulting', 'custom'])],
            'body_html' => ['required', 'string', 'max:2000000'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $contract->update($data);

        return redirect()
            ->route('contracts.show', $contract)
            ->with('status', __('contract_saved'));
    }

    public function destroy(Request $request, ClientContract $contract): RedirectResponse
    {
        $this->authorizeContract($request, $contract);
        $contract->delete();

        return redirect()
            ->route('contracts.index')
            ->with('status', __('contract_deleted'));
    }

    public function send(Request $request, ClientContract $contract, ContractMergeService $merge): RedirectResponse
    {
        $this->authorizeContract($request, $contract);

        if (! $contract->canSend()) {
            return redirect()
                ->route('contracts.show', $contract)
                ->with('error', __('contract_send_not_draft'));
        }

        $contract->loadMissing(['client', 'company']);
        $baked = $merge->bake($contract);

        $contract->update([
            'body_html' => $baked,
            'status' => ClientContractStatus::Sent,
            'sent_at' => now(),
            'company_signed_at' => now(),
        ]);

        try {
            $this->notifyClient($contract, app(TenantSmtpMailer::class));
        } catch (RuntimeException $e) {
            return redirect()
                ->route('contracts.show', $contract)
                ->with('status', __('contract_sent'))
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('contracts.show', $contract)
            ->with('status', __('contract_sent'));
    }

    public function resend(Request $request, ClientContract $contract, TenantSmtpMailer $mailer): RedirectResponse
    {
        $this->authorizeContract($request, $contract);

        if (! $contract->canResend()) {
            return redirect()
                ->route('contracts.show', $contract)
                ->with('error', __('contract_resend_not_allowed'));
        }

        $contract->loadMissing(['client', 'company']);

        $clientEmail = $contract->client?->email;
        if ($clientEmail === null || $clientEmail === '') {
            return redirect()
                ->route('contracts.show', $contract)
                ->with('error', __('contract_resend_no_email'));
        }

        try {
            $this->notifyClient($contract, $mailer);
        } catch (RuntimeException $e) {
            return redirect()
                ->route('contracts.show', $contract)
                ->with('error', $e->getMessage());
        }

        $contract->update(['sent_at' => now()]);

        return redirect()
            ->route('contracts.show', $contract)
            ->with('status', __('contract_resent', ['email' => $clientEmail]));
    }

    public function pdf(Request $request, ClientContract $contract, ContractPdfService $pdfs): StreamedResponse
    {
        $this->authorizeContract($request, $contract);

        return response()->streamDownload(function () use ($contract, $pdfs): void {
            echo $pdfs->output($contract);
        }, 'contract-'.($contract->reference ?? $contract->id).'.pdf', [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function sample(Request $request, string $type, string $locale, ContractSampleService $samples): \Illuminate\Http\JsonResponse
    {
        abort_if(! $request->user()->company, 403);
        abort_unless(in_array($type, $samples->typeKeys(), true), 404);
        abort_unless(in_array($locale, $samples->locales(), true), 404);

        return response()->json([
            'html' => $samples->html($type, $locale),
            'type' => $type,
            'locale' => $locale,
        ]);
    }

    /**
     * Live preview of a company template (optionally merged with a client).
     */
    public function previewTemplate(Request $request, ContractMergeService $merge): \Illuminate\Http\JsonResponse
    {
        $company = $request->user()->company;
        abort_if(! $company, 403);

        $data = $request->validate([
            'contract_template_id' => [
                'nullable',
                'string',
                Rule::exists('contract_templates', 'id')->where(fn ($q) => $q->where('company_id', $company->id)),
            ],
            'client_id' => [
                'nullable',
                'string',
                Rule::exists('clients', 'id')->where(fn ($q) => $q->where('company_id', $company->id)),
            ],
            'title' => ['nullable', 'string', 'max:255'],
            'body_html' => ['nullable', 'string', 'max:2000000'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date'],
            'reference' => ['nullable', 'string', 'max:60'],
        ]);

        $body = (string) ($data['body_html'] ?? '');
        $title = trim((string) ($data['title'] ?? ''));
        $typeKey = 'custom';

        if (! empty($data['contract_template_id'])) {
            $template = ContractTemplate::query()
                ->withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->whereKey($data['contract_template_id'])
                ->firstOrFail();
            if ($body === '') {
                $body = (string) $template->body_html;
            }
            if ($title === '') {
                $title = $template->name;
            }
            $typeKey = $template->type_key;
        }

        $client = null;
        if (! empty($data['client_id'])) {
            $client = Client::query()
                ->withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->whereKey($data['client_id'])
                ->first();
        }

        $draft = new ClientContract([
            'company_id' => $company->id,
            'client_id' => $client?->id,
            'title' => $title !== '' ? $title : __('Contract'),
            'type_key' => $typeKey,
            'body_html' => $body,
            'reference' => $data['reference'] ?? 'CTR-PREVIEW',
            'starts_on' => $data['starts_on'] ?? null,
            'ends_on' => $data['ends_on'] ?? null,
        ]);
        $draft->setRelation('company', $company);
        $draft->setRelation('client', $client);

        return response()->json([
            'html' => $body !== '' ? $merge->interpolate($body, $draft) : '',
            'raw' => $body,
            'title' => $draft->title,
        ]);
    }

    /**
     * @throws RuntimeException
     */
    private function notifyClient(ClientContract $contract, TenantSmtpMailer $mailer): void
    {
        $clientEmail = $contract->client?->email;
        if ($clientEmail === null || $clientEmail === '') {
            throw new RuntimeException(__('contract_resend_no_email'));
        }

        $company = $contract->company;
        if (! $company) {
            throw new RuntimeException(__('tenant_smtp_required'));
        }

        $mailable = new ContractSentMail($contract, $company);
        $mailer->send($company, $mailable, $clientEmail, requireSmtp: false);
    }

    private function authorizeContract(Request $request, ClientContract $contract): void
    {
        abort_if((string) $contract->company_id !== (string) $request->user()->company_id, 404);
    }
}
