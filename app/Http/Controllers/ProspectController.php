<?php

namespace App\Http\Controllers;

use App\Enums\ProspectSource;
use App\Enums\ProspectStatus;
use App\Enums\ProspectVerifiedVia;
use App\Models\Prospect;
use App\Services\ProspectService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProspectController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            abort_if(! $request->user()->hasAnyRole(['company_admin', 'team_member']), 403);

            return $next($request);
        });
    }

    public function index(Request $request): View
    {
        $company = $request->user()->company;
        abort_if(! $company, 403);

        $q = $request->string('q')->trim()->toString();
        $status = $request->string('status')->trim()->toString();
        $source = $request->string('source')->trim()->toString();

        $query = Prospect::query()
            ->withoutGlobalScope('tenant')
            ->where('company_id', $company->id)
            ->latest();

        if ($q !== '') {
            $query->where(function ($builder) use ($q) {
                $builder->where('name', 'like', '%'.$q.'%')
                    ->orWhere('email', 'like', '%'.$q.'%')
                    ->orWhere('company_name', 'like', '%'.$q.'%')
                    ->orWhere('phone', 'like', '%'.$q.'%')
                    ->orWhere('whatsapp', 'like', '%'.$q.'%');
            });
        }

        if ($status !== '' && in_array($status, ProspectStatus::values(), true)) {
            $query->where('status', $status);
        }

        if ($source !== '' && in_array($source, ProspectSource::values(), true)) {
            $query->where('source', $source);
        }

        $prospects = $query->paginate(20)->withQueryString();

        return view('prospects.index', compact('prospects', 'q', 'status', 'source'));
    }

    public function create(): View
    {
        abort_if(! auth()->user()?->company, 403);

        return view('prospects.create');
    }

    public function store(Request $request, ProspectService $prospects): RedirectResponse
    {
        $company = $request->user()->company;
        abort_if(! $company, 403);

        $data = $this->validatedProspect($request);

        $email = isset($data['email']) ? strtolower(trim((string) $data['email'])) : null;
        if ($email === '') {
            $email = null;
        }

        if ($email !== null) {
            $exists = Prospect::query()
                ->withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->where('email', $email)
                ->exists();
            abort_if($exists, 422, __('prospect_email_already_exists'));
        }

        $prospect = Prospect::query()->create([
            'company_id' => $company->id,
            'name' => $data['name'],
            'email' => $email,
            'phone' => $data['phone'] ?? null,
            'whatsapp' => $data['whatsapp'] ?? null,
            'company_name' => $data['company_name'] ?? null,
            'job_title' => $data['job_title'] ?? null,
            'socials' => $this->socialsFromRequest($data),
            'source' => $data['source'] ?? ProspectSource::Manual->value,
            'status' => ProspectStatus::New,
            'notes' => $data['notes'] ?? null,
        ]);

        $prospects->ensureSourceActivity($prospect, $request->user());

        return redirect()
            ->route('prospects.show', $prospect)
            ->with('status', __('prospect_saved'));
    }

    public function show(Request $request, Prospect $prospect, ProspectService $prospects): View
    {
        $this->assertProspect($request, $prospect);
        $prospect->load(['convertedClient', 'activities.user']);
        $prospects->ensureSourceActivity($prospect, $request->user());
        $prospect->load(['activities.user']);

        $lifecycle = $prospect->lifecycleSteps();
        $nextStep = $prospect->nextLifecycleStep();
        $latestAi = $prospect->activities
            ->first(fn ($a) => $a->type === \App\Enums\ProspectActivityType::AiSuggestion);

        $defaultPanel = $request->string('panel')->toString();
        if ($defaultPanel === '') {
            $defaultPanel = $nextStep['panel'] ?? 'call';
        }

        return view('prospects.show', compact('prospect', 'lifecycle', 'latestAi', 'nextStep', 'defaultPanel'));
    }

    public function edit(Request $request, Prospect $prospect): View
    {
        $this->assertProspect($request, $prospect);

        return view('prospects.edit', compact('prospect'));
    }

    public function update(Request $request, Prospect $prospect): RedirectResponse
    {
        $this->assertProspect($request, $prospect);
        $company = $request->user()->company;
        abort_if(! $company, 403);

        $data = $this->validatedProspect($request, $prospect);

        $email = isset($data['email']) ? strtolower(trim((string) $data['email'])) : null;
        if ($email === '') {
            $email = null;
        }

        if ($email !== null) {
            $exists = Prospect::query()
                ->withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->where('email', $email)
                ->whereKeyNot($prospect->id)
                ->exists();
            abort_if($exists, 422, __('prospect_email_already_exists'));
        }

        $prospect->update([
            'name' => $data['name'],
            'email' => $email,
            'phone' => $data['phone'] ?? null,
            'whatsapp' => $data['whatsapp'] ?? null,
            'company_name' => $data['company_name'] ?? null,
            'job_title' => $data['job_title'] ?? null,
            'socials' => $this->socialsFromRequest($data),
            'source' => $data['source'] ?? $prospect->source,
            'status' => $data['status'] ?? $prospect->status,
            'notes' => $data['notes'] ?? null,
        ]);

        return redirect()
            ->route('prospects.show', $prospect)
            ->with('status', __('prospect_saved'));
    }

    public function destroy(Request $request, Prospect $prospect): RedirectResponse
    {
        $this->assertProspect($request, $prospect);
        $prospect->delete();

        return redirect()
            ->route('prospects.index')
            ->with('status', __('prospect_deleted'));
    }

    public function importForm(Request $request): View
    {
        abort_if(! $request->user()->company, 403);

        return view('prospects.import');
    }

    public function importPreview(Request $request, ProspectService $prospects): View|RedirectResponse
    {
        abort_if(! $request->user()->company, 403);

        $raw = $this->rawImportPayload($request);
        if ($raw === '') {
            return back()->withErrors(['contacts_input' => __('prospect_import_empty')])->withInput();
        }

        $rows = $prospects->parseDelimited($raw);
        if ($rows === []) {
            return back()->withErrors(['contacts_input' => __('prospect_import_empty')])->withInput();
        }

        $preview = array_slice($rows, 0, 25);

        return view('prospects.import-preview', [
            'rows' => $rows,
            'preview' => $preview,
            'raw' => $raw,
            'filename' => $request->file('csv_file')?->getClientOriginalName(),
        ]);
    }

    public function importStore(Request $request, ProspectService $prospects): RedirectResponse
    {
        $company = $request->user()->company;
        abort_if(! $company, 403);

        $data = $request->validate([
            'raw' => ['required', 'string', 'max:2000000'],
            'filename' => ['nullable', 'string', 'max:255'],
        ]);

        $rows = $prospects->parseDelimited($data['raw']);
        if ($rows === []) {
            return redirect()
                ->route('prospects.import')
                ->withErrors(['contacts_input' => __('prospect_import_empty')]);
        }

        $result = $prospects->importRows($company, $request->user(), $rows, $data['filename'] ?? null);

        return redirect()
            ->route('prospects.index')
            ->with('status', __('prospect_import_done', [
                'created' => $result['created'],
                'updated' => $result['updated'],
                'errors' => count($result['errors']),
            ]));
    }

    public function verify(Request $request, Prospect $prospect): RedirectResponse
    {
        $this->assertProspect($request, $prospect);

        if ($prospect->status === ProspectStatus::Converted) {
            return back()->with('status', __('prospect_already_converted'));
        }

        $prospect->forceFill([
            'status' => ProspectStatus::Verified,
            'verified_at' => now(),
            'verified_via' => ProspectVerifiedVia::Manual,
        ])->save();

        return back()->with('status', __('prospect_verified'));
    }

    public function convert(Request $request, Prospect $prospect, ProspectService $prospects): RedirectResponse
    {
        $this->assertProspect($request, $prospect);

        if ($prospect->status === ProspectStatus::Converted && $prospect->converted_client_id) {
            return redirect()
                ->route('clients.show', $prospect->converted_client_id)
                ->with('status', __('prospect_already_converted'));
        }

        $client = $prospects->convertToClient($prospect, \App\Enums\ClientStatus::Active, $request->user());

        return redirect()
            ->route('clients.show', $client)
            ->with('status', __('prospect_converted'));
    }

    public function storeCall(Request $request, Prospect $prospect, ProspectService $prospects): RedirectResponse
    {
        $this->assertProspect($request, $prospect);
        $this->assertNotConverted($prospect);

        $data = $request->validate([
            'summary' => ['required', 'string', 'max:5000'],
            'outcome' => ['nullable', 'string', 'max:255'],
            'occurred_at' => ['nullable', 'date'],
        ]);

        $prospects->logCall(
            $prospect,
            $request->user(),
            $data['summary'],
            $data['outcome'] ?? null,
            isset($data['occurred_at']) ? \Illuminate\Support\Carbon::parse($data['occurred_at']) : null,
        );

        return $this->redirectToNextStep($prospect, __('prospect_call_saved'));
    }

    public function storeFeedback(Request $request, Prospect $prospect, ProspectService $prospects): RedirectResponse
    {
        $this->assertProspect($request, $prospect);
        $this->assertNotConverted($prospect);

        $data = $request->validate([
            'feedback' => ['required', 'string', 'max:5000'],
            'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
        ]);

        $prospects->logFeedback(
            $prospect,
            $request->user(),
            $data['feedback'],
            isset($data['rating']) ? (int) $data['rating'] : null,
        );

        return $this->redirectToNextStep($prospect, __('prospect_feedback_saved'));
    }

    public function storeMeeting(Request $request, Prospect $prospect, ProspectService $prospects): RedirectResponse
    {
        $this->assertProspect($request, $prospect);
        $this->assertNotConverted($prospect);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'date' => ['required', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $prospects->scheduleMeeting(
            $prospect,
            $request->user(),
            $data['title'],
            $data['date'],
            $data['start_time'] ?? null,
            $data['notes'] ?? null,
        );

        return $this->redirectToNextStep($prospect, __('prospect_meeting_saved'));
    }

    public function prepareQuote(Request $request, Prospect $prospect, ProspectService $prospects): RedirectResponse
    {
        $this->assertProspect($request, $prospect);
        $this->assertNotConverted($prospect);

        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $client = $prospects->prepareQuote($prospect, $request->user(), $data['notes'] ?? null);

        return redirect()
            ->route('proposals.create', ['client' => $client->id])
            ->with('status', __('prospect_quote_ready'));
    }

    public function storeMail(Request $request, Prospect $prospect, ProspectService $prospects): RedirectResponse
    {
        $this->assertProspect($request, $prospect);
        $this->assertNotConverted($prospect);
        $company = $request->user()->company;
        abort_if(! $company, 403);

        $data = $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:20000'],
        ]);

        try {
            $prospects->sendMail($prospect, $request->user(), $company, $data['subject'], $data['body']);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['email' => $e->getMessage()])->withInput();
        }

        return $this->redirectToNextStep($prospect, __('prospect_mail_sent'));
    }

    public function storeReminder(Request $request, Prospect $prospect, ProspectService $prospects): RedirectResponse
    {
        $this->assertProspect($request, $prospect);
        $this->assertNotConverted($prospect);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'date' => ['required', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $prospects->scheduleReminder(
            $prospect,
            $request->user(),
            $data['title'],
            $data['date'],
            $data['start_time'] ?? null,
            $data['notes'] ?? null,
        );

        return $this->redirectToNextStep($prospect, __('prospect_reminder_saved'));
    }

    public function generateAiCatch(Request $request, Prospect $prospect, ProspectService $prospects): RedirectResponse
    {
        $this->assertProspect($request, $prospect);
        $this->assertNotConverted($prospect);
        $company = $request->user()->company;
        abort_if(! $company, 403);

        try {
            $prospects->generateCatchProposal($prospect, $request->user(), $company);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['ai' => $e->getMessage()]);
        }

        return $this->redirectToNextStep($prospect, __('prospect_ai_generated'));
    }

    private function redirectToNextStep(Prospect $prospect, string $status): RedirectResponse
    {
        $prospect->refresh()->load('activities');
        $next = $prospect->nextLifecycleStep();

        return redirect()
            ->route('prospects.show', [
                'prospect' => $prospect,
                'panel' => $next['panel'] ?? 'convert',
            ])
            ->with('status', $status);
    }

    private function assertNotConverted(Prospect $prospect): void
    {
        abort_if($prospect->status === ProspectStatus::Converted, 422, __('prospect_already_converted'));
    }

    private function assertProspect(Request $request, Prospect $prospect): void
    {
        $company = $request->user()->company;
        abort_if(! $company, 403);
        abort_if((string) $prospect->company_id !== (string) $company->id, 404);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedProspect(Request $request, ?Prospect $prospect = null): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:60'],
            'whatsapp' => ['nullable', 'string', 'max:60'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'linkedin' => ['nullable', 'string', 'max:500'],
            'twitter' => ['nullable', 'string', 'max:500'],
            'facebook' => ['nullable', 'string', 'max:500'],
            'instagram' => ['nullable', 'string', 'max:500'],
            'website' => ['nullable', 'string', 'max:500'],
            'source' => ['nullable', Rule::in(ProspectSource::values())],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];

        if ($prospect !== null) {
            $rules['status'] = ['nullable', Rule::in(ProspectStatus::values())];
        }

        return $request->validate($rules);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    private function socialsFromRequest(array $data): array
    {
        $socials = [];
        foreach (['linkedin', 'twitter', 'facebook', 'instagram', 'website'] as $key) {
            $value = isset($data[$key]) ? trim((string) $data[$key]) : '';
            if ($value !== '') {
                $socials[$key] = $value;
            }
        }

        return $socials;
    }

    private function rawImportPayload(Request $request): string
    {
        $request->validate([
            'contacts_input' => ['nullable', 'string', 'max:2000000'],
            'csv_file' => ['nullable', 'file', 'mimes:csv,txt,tsv', 'max:5120'],
        ]);

        if ($request->hasFile('csv_file')) {
            return (string) file_get_contents($request->file('csv_file')->getRealPath());
        }

        return trim((string) $request->input('contacts_input', ''));
    }
}
