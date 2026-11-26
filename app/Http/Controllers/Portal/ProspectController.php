<?php

namespace App\Http\Controllers\Portal;

use App\Enums\ClientAppointmentStatus;
use App\Enums\ClientProspectStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Concerns\ResolvesPortalClient;
use App\Models\ClientCampaign;
use App\Models\ClientProspect;
use App\Services\ClientProspectService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProspectController extends Controller
{
    use ResolvesPortalClient;

    public function __construct(
        private ClientProspectService $prospects,
    ) {}

    public function index(Request $request): View
    {
        $client = $this->portalClient($request);
        $prospects = $this->filteredQuery($request, $client->id)
            ->orderByDesc('captured_on')
            ->orderByDesc('created_at')
            ->paginate(50)
            ->withQueryString();

        $campaigns = ClientCampaign::query()
            ->where('client_id', $client->id)
            ->orderBy('name')
            ->pluck('name', 'id');
        if ($campaigns->isEmpty()) {
            $campaigns = ClientProspect::query()
                ->where('client_id', $client->id)
                ->whereNotNull('campaign')
                ->where('campaign', '!=', '')
                ->distinct()
                ->orderBy('campaign')
                ->pluck('campaign', 'campaign');
        }

        return view('portal.prospects.index', [
            'client' => $client,
            'prospects' => $prospects,
            'campaigns' => $campaigns,
            'campaignRecords' => ClientCampaign::query()->where('client_id', $client->id)->orderBy('name')->get(['id', 'name']),
            'statuses' => ClientProspectStatus::cases(),
            'filters' => [
                'q' => $request->string('q')->trim()->toString(),
                'status' => $request->string('status')->toString(),
                'campaign' => $request->string('campaign')->toString(),
            ],
        ]);
    }

    public function show(Request $request, ClientProspect $prospect): View
    {
        $client = $this->portalClient($request);
        $this->authorizeProspect($client, $prospect);
        $prospect->load(['comments.author', 'campaignRelation', 'creator']);

        return view('portal.prospects.show', [
            'client' => $client,
            'prospect' => $prospect,
            'statuses' => ClientProspectStatus::cases(),
            'appointmentStatuses' => ClientAppointmentStatus::cases(),
            'campaigns' => ClientCampaign::query()->where('client_id', $client->id)->orderBy('name')->get(['id', 'name']),
            'canEditFields' => $this->canEditFields($request, $prospect),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $client = $this->portalClient($request);
        $data = $request->validate($this->prospectRules(true));
        $data['source'] = 'client';
        $prospect = $this->prospects->create($client, $data, $request->user());

        return redirect()
            ->route('portal.prospects.show', $prospect)
            ->with('status', __('portal_prospect_created'));
    }

    public function update(Request $request, ClientProspect $prospect): RedirectResponse
    {
        $client = $this->portalClient($request);
        $this->authorizeProspect($client, $prospect);
        $data = $request->validate($this->prospectRules(false));
        if (! $this->canEditFields($request, $prospect)) {
            $data = array_intersect_key($data, array_flip(['status', 'comment', 'appointment_at', 'appointment_status']));
        }
        $this->prospects->update($prospect, $data, $request->user());

        return back()->with('status', __('portal_prospect_updated'));
    }

    public function appointments(Request $request): View
    {
        $client = $this->portalClient($request);
        $tab = $request->string('tab')->toString() === 'past' ? 'past' : 'upcoming';

        $query = ClientProspect::query()
            ->where('client_id', $client->id)
            ->where(function ($q): void {
                $q->where('status', ClientProspectStatus::RdvProgramme)
                    ->orWhereNotNull('appointment_at');
            })
            ->with(['campaignRelation', 'comments' => fn ($q) => $q->limit(5)]);

        if ($tab === 'past') {
            $query->where(function ($q): void {
                $q->whereNotNull('appointment_at')->where('appointment_at', '<', now())
                    ->orWhereIn('appointment_status', [
                        ClientAppointmentStatus::Venu->value,
                        ClientAppointmentStatus::PasVenu->value,
                        ClientAppointmentStatus::Annule->value,
                    ]);
            });
            $query->orderByDesc('appointment_at');
        } else {
            $query->where(function ($q): void {
                $q->whereNull('appointment_at')
                    ->orWhere('appointment_at', '>=', now()->startOfDay());
            })->where(function ($q): void {
                $q->whereNull('appointment_status')
                    ->orWhereNotIn('appointment_status', [
                        ClientAppointmentStatus::Venu->value,
                        ClientAppointmentStatus::PasVenu->value,
                        ClientAppointmentStatus::Annule->value,
                    ]);
            });
            $query->orderByRaw('appointment_at is null')
                ->orderBy('appointment_at');
        }

        $prospects = $query->get();
        $grouped = $prospects->groupBy(function (ClientProspect $p) {
            return $p->appointment_at?->timezone(config('app.timezone'))->format('Y-m-d') ?? 'unset';
        });

        return view('portal.prospects.appointments', [
            'client' => $client,
            'grouped' => $grouped,
            'tab' => $tab,
            'statuses' => ClientProspectStatus::cases(),
            'appointmentStatuses' => ClientAppointmentStatus::cases(),
            'upcomingCount' => ClientProspect::query()
                ->where('client_id', $client->id)
                ->where(function ($q): void {
                    $q->where('status', ClientProspectStatus::RdvProgramme)->orWhereNotNull('appointment_at');
                })
                ->where(function ($q): void {
                    $q->whereNull('appointment_at')->orWhere('appointment_at', '>=', now()->startOfDay());
                })
                ->where(function ($q): void {
                    $q->whereNull('appointment_status')
                        ->orWhereNotIn('appointment_status', [
                            ClientAppointmentStatus::Venu->value,
                            ClientAppointmentStatus::PasVenu->value,
                            ClientAppointmentStatus::Annule->value,
                        ]);
                })
                ->count(),
            'pastCount' => ClientProspect::query()
                ->where('client_id', $client->id)
                ->where(function ($q): void {
                    $q->where('status', ClientProspectStatus::RdvProgramme)->orWhereNotNull('appointment_at');
                })
                ->where(function ($q): void {
                    $q->where(function ($q2): void {
                        $q2->whereNotNull('appointment_at')->where('appointment_at', '<', now());
                    })->orWhereIn('appointment_status', [
                        ClientAppointmentStatus::Venu->value,
                        ClientAppointmentStatus::PasVenu->value,
                        ClientAppointmentStatus::Annule->value,
                    ]);
                })
                ->count(),
        ]);
    }

    public function updateStatus(Request $request, ClientProspect $prospect): RedirectResponse
    {
        $client = $this->portalClient($request);
        $this->authorizeProspect($client, $prospect);

        $data = $request->validate([
            'status' => ['required', 'string', Rule::enum(ClientProspectStatus::class)],
            'appointment_at' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $status = ClientProspectStatus::from($data['status']);
        if (array_key_exists('appointment_at', $data)) {
            $prospect->update(['appointment_at' => $data['appointment_at'] ?: null]);
        }
        $this->prospects->changeStatus($prospect, $status, $request->user(), $data['note'] ?? null);

        return back()->with('status', __('client_prospect_status_saved'));
    }

    public function updateAppointment(Request $request, ClientProspect $prospect): RedirectResponse
    {
        $client = $this->portalClient($request);
        $this->authorizeProspect($client, $prospect);

        $data = $request->validate([
            'appointment_status' => ['nullable', 'string', Rule::enum(ClientAppointmentStatus::class)],
            'appointment_at' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $payload = [];
        if (array_key_exists('appointment_at', $data) && $data['appointment_at']) {
            $payload['appointment_at'] = $data['appointment_at'];
            $payload['status'] = ClientProspectStatus::RdvProgramme;
        }
        if (! empty($data['appointment_status'])) {
            $status = ClientAppointmentStatus::from($data['appointment_status']);
            $payload['appointment_status'] = $status;
            if ($status === ClientAppointmentStatus::PasVenu) {
                $payload['status'] = ClientProspectStatus::PasVenu;
            }
        }
        if ($payload !== []) {
            $this->prospects->update($prospect, $payload, $request->user());
        }
        if (! empty($data['note'])) {
            $this->prospects->addComment($prospect, $data['note'], $request->user());
        }

        return back()->with('status', __('portal_appointment_updated'));
    }

    public function comment(Request $request, ClientProspect $prospect): RedirectResponse
    {
        $client = $this->portalClient($request);
        $this->authorizeProspect($client, $prospect);

        $data = $request->validate([
            'comment' => ['required', 'string', 'max:5000'],
        ]);

        $this->prospects->addComment($prospect, $data['comment'], $request->user());

        return back()->with('status', __('client_prospect_comment_saved'));
    }

    private function authorizeProspect($client, ClientProspect $prospect): void
    {
        abort_if((string) $prospect->company_id !== (string) $client->company_id, 403);
        abort_if((string) $prospect->client_id !== (string) $client->id, 403);
    }

    private function canEditFields(Request $request, ClientProspect $prospect): bool
    {
        if (($prospect->source ?? '') === 'client') {
            return true;
        }

        return (string) $prospect->created_by === (string) $request->user()?->id;
    }

    /** @return array<string, mixed> */
    private function prospectRules(bool $creating): array
    {
        return [
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:60'],
            'email' => ['nullable', 'email', 'max:255'],
            'campaign' => ['nullable', 'string', 'max:255'],
            'campaign_id' => ['nullable', 'string', 'max:26'],
            'comment' => ['nullable', 'string', 'max:5000'],
            'status' => ['nullable', 'string', Rule::enum(ClientProspectStatus::class)],
            'captured_on' => ['nullable', 'date'],
            'appointment_at' => ['nullable', 'date'],
            'appointment_status' => ['nullable', 'string', Rule::enum(ClientAppointmentStatus::class)],
        ];
    }

    private function filteredQuery(Request $request, string $clientId)
    {
        $query = ClientProspect::query()
            ->where('client_id', $clientId)
            ->with(['comments' => fn ($q) => $q->limit(8), 'campaignRelation:id,name', 'creator:id,name']);

        $q = $request->string('q')->trim()->toString();
        if ($q !== '') {
            $query->where(function ($builder) use ($q): void {
                $builder->where('name', 'like', '%'.$q.'%')
                    ->orWhere('phone', 'like', '%'.$q.'%')
                    ->orWhere('email', 'like', '%'.$q.'%')
                    ->orWhere('campaign', 'like', '%'.$q.'%')
                    ->orWhere('comment', 'like', '%'.$q.'%');
            });
        }

        $status = $request->string('status')->toString();
        if ($status !== '' && ClientProspectStatus::tryFrom($status)) {
            $query->where('status', $status);
        }

        $campaign = $request->string('campaign')->toString();
        $campaignId = $request->string('campaign_id')->toString();
        if ($campaignId !== '') {
            $query->where('campaign_id', $campaignId);
        } elseif ($campaign !== '') {
            $query->where(function ($b) use ($campaign): void {
                $b->where('campaign', $campaign)
                    ->orWhereHas('campaignRelation', fn ($q) => $q->where('name', $campaign));
            });
        }

        return $query;
    }
}
