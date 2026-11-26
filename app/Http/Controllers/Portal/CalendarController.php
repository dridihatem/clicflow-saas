<?php

namespace App\Http\Controllers\Portal;

use App\Enums\ClientAppointmentStatus;
use App\Enums\ClientProspectStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Concerns\ResolvesPortalClient;
use App\Models\ClientBooking;
use App\Models\ClientProspect;
use App\Models\Project;
use App\Services\ClientProspectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CalendarController extends Controller
{
    use ResolvesPortalClient;

    public function __construct(private ClientProspectService $prospects) {}

    public function index(Request $request): View
    {
        $client = $this->portalClient($request);
        $view = in_array($request->string('view')->toString(), ['month', 'week', 'day'], true)
            ? $request->string('view')->toString()
            : 'month';
        $anchor = $this->anchor($request);

        return view('portal.calendar.index', [
            'client' => $client,
            'view' => $view,
            'anchor' => $anchor->toDateString(),
            'events' => $this->eventsFor($client->id, $anchor->copy()->subMonth()->startOfMonth(), $anchor->copy()->addMonth()->endOfMonth()),
            'prospects' => ClientProspect::query()
                ->where('client_id', $client->id)
                ->orderBy('name')
                ->get(['id', 'name', 'appointment_at', 'status']),
        ]);
    }

    public function events(Request $request): JsonResponse
    {
        $client = $this->portalClient($request);
        $from = Carbon::parse($request->string('from')->toString() ?: now()->startOfMonth())->startOfDay();
        $to = Carbon::parse($request->string('to')->toString() ?: now()->endOfMonth())->endOfDay();

        return response()->json(['events' => $this->eventsFor($client->id, $from, $to)]);
    }

    public function preview(Request $request): JsonResponse
    {
        $client = $this->portalClient($request);
        $anchor = $this->anchor($request);
        $events = $this->eventsFor($client->id, $anchor->copy()->startOfMonth(), $anchor->copy()->endOfMonth());

        return response()->json([
            'month' => $anchor->format('Y-m'),
            'events' => $events,
        ]);
    }

    public function storeRdv(Request $request): RedirectResponse
    {
        $client = $this->portalClient($request);
        $data = $request->validate([
            'prospect_id' => ['required', 'string', 'max:26'],
            'appointment_at' => ['required', 'date'],
            'appointment_status' => ['nullable', 'string', Rule::enum(ClientAppointmentStatus::class)],
        ]);

        $prospect = ClientProspect::query()
            ->where('client_id', $client->id)
            ->where('id', $data['prospect_id'])
            ->firstOrFail();

        $this->prospects->update($prospect, [
            'appointment_at' => $data['appointment_at'],
            'appointment_status' => $data['appointment_status'] ?? ClientAppointmentStatus::Confirme->value,
            'status' => ClientProspectStatus::RdvProgramme,
        ], $request->user());

        return redirect()
            ->route('portal.prospects.show', $prospect)
            ->with('status', __('portal_appointment_created'));
    }

    private function anchor(Request $request): Carbon
    {
        $value = $request->string('date')->toString() ?: $request->string('month')->toString();
        if ($value && preg_match('/^\d{4}-\d{2}(-\d{2})?$/', $value)) {
            return Carbon::parse($value)->startOfDay();
        }

        return now()->startOfDay();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function eventsFor(string $clientId, Carbon $from, Carbon $to): array
    {
        $events = [];

        $prospects = ClientProspect::query()
            ->where('client_id', $clientId)
            ->whereNotNull('appointment_at')
            ->whereBetween('appointment_at', [$from, $to])
            ->get();
        foreach ($prospects as $p) {
            $st = $p->appointment_status instanceof ClientAppointmentStatus
                ? $p->appointment_status
                : ClientAppointmentStatus::tryFrom((string) $p->appointment_status) ?? ClientAppointmentStatus::Confirme;
            $events[] = [
                'id' => 'prospect-'.$p->id,
                'type' => 'prospect',
                'title' => $p->name,
                'start' => $p->appointment_at->toIso8601String(),
                'status' => $st->value,
                'status_label' => $st->label(),
                'color' => $st->calendarColor(),
                'url' => route('portal.prospects.show', $p),
            ];
        }

        $bookings = ClientBooking::query()
            ->where('client_id', $clientId)
            ->whereNotNull('starts_at')
            ->whereBetween('starts_at', [$from, $to])
            ->get();
        foreach ($bookings as $b) {
            $events[] = [
                'id' => 'booking-'.$b->id,
                'type' => 'booking',
                'title' => trim($b->customer_name.($b->service ? ' · '.$b->service : '')),
                'start' => $b->starts_at->toIso8601String(),
                'status' => $b->status?->value,
                'status_label' => $b->status?->label(),
                'color' => '#ea580c',
                'url' => route('portal.bookings.index', ['q' => $b->customer_name]),
            ];
        }

        if (class_exists(Project::class)) {
            $projects = Project::query()
                ->where('client_id', $clientId)
                ->whereNotNull('final_deadline')
                ->whereBetween('final_deadline', [$from, $to])
                ->get(['id', 'title', 'final_deadline']);
            foreach ($projects as $project) {
                $events[] = [
                    'id' => 'project-'.$project->id,
                    'type' => 'project',
                    'title' => $project->title ?: __('Project'),
                    'start' => Carbon::parse($project->final_deadline)->startOfDay()->toIso8601String(),
                    'status' => 'deadline',
                    'status_label' => __('portal_calendar_deadline'),
                    'color' => '#7c3aed',
                    'url' => route('portal.projects.show', $project),
                ];
            }
        }

        return $events;
    }
}
