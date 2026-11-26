<?php

namespace App\Http\Controllers\Api\Client;

use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Concerns\ResolvesPortalClient;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Proposal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PortalController extends Controller
{
    use ResolvesPortalClient;

    public function dashboard(Request $request): JsonResponse
    {
        $client = $this->portalClient($request);

        return response()->json([
            'stats' => [
                'projects' => $client->projects()->count(),
                'proposals' => Proposal::query()
                    ->withoutGlobalScopes()
                    ->where('company_id', $client->company_id)
                    ->where('client_id', $client->id)
                    ->count(),
                'invoices' => Invoice::query()
                    ->withoutGlobalScopes()
                    ->where('company_id', $client->company_id)
                    ->where('client_id', $client->id)
                    ->count(),
                'open_invoices' => Invoice::query()
                    ->withoutGlobalScopes()
                    ->where('company_id', $client->company_id)
                    ->where('client_id', $client->id)
                    ->whereNotIn('status', ['paid', 'cancelled', 'void'])
                    ->count(),
            ],
        ]);
    }

    public function projects(Request $request): JsonResponse
    {
        $client = $this->portalClient($request);
        $q = trim((string) $request->query('q', ''));
        $status = $request->query('status');

        $query = $client->projects()
            ->with(['provider:id,name'])
            ->withCount([
                'tasks',
                'tasks as done_tasks_count' => fn ($builder) => $builder->where('status', TaskStatus::Done->value),
            ]);

        if ($q !== '') {
            $query->where(function ($builder) use ($q) {
                $builder->where('title', 'like', '%'.$q.'%')
                    ->orWhereHas('provider', fn ($provider) => $provider->where('name', 'like', '%'.$q.'%'));
            });
        }

        if (is_string($status) && $status !== '' && in_array($status, array_column(ProjectStatus::cases(), 'value'), true)) {
            $query->where('status', $status);
        }

        $projects = $query->latest()->paginate(20);

        return response()->json($projects);
    }

    public function project(Request $request, Project $project): JsonResponse
    {
        $client = $this->portalClient($request);
        $this->authorizePortalProject($client, $project);

        $project->load([
            'provider:id,name',
            'tasks' => fn ($q) => $q->orderBy('sort_order')->limit(100),
        ]);

        $doneCount = $project->tasks->filter(function ($t) {
            $status = $t->status;

            return $status === TaskStatus::Done
                || $status === TaskStatus::Done->value
                || (is_object($status) && ($status->value ?? null) === TaskStatus::Done->value);
        })->count();
        $totalTasks = $project->tasks->count();

        return response()->json([
            'project' => $project,
            'progress_pct' => $totalTasks > 0 ? (int) round(($doneCount / $totalTasks) * 100) : 0,
            'done_tasks_count' => $doneCount,
            'total_tasks' => $totalTasks,
        ]);
    }

    public function proposals(Request $request): JsonResponse
    {
        $client = $this->portalClient($request);

        $proposals = Proposal::query()
            ->withoutGlobalScopes()
            ->where('company_id', $client->company_id)
            ->where('client_id', $client->id)
            ->latest()
            ->paginate(20);

        return response()->json($proposals);
    }

    public function proposal(Request $request, Proposal $proposal): JsonResponse
    {
        $client = $this->portalClient($request);
        $this->authorizePortalProposal($client, $proposal);

        return response()->json(['proposal' => $proposal]);
    }

    public function invoices(Request $request): JsonResponse
    {
        $client = $this->portalClient($request);

        $invoices = Invoice::query()
            ->withoutGlobalScopes()
            ->where('company_id', $client->company_id)
            ->where('client_id', $client->id)
            ->latest()
            ->paginate(20);

        return response()->json($invoices);
    }

    public function invoice(Request $request, Invoice $invoice): JsonResponse
    {
        $client = $this->portalClient($request);
        $this->authorizePortalInvoice($client, $invoice);

        return response()->json(['invoice' => $invoice]);
    }

    public function messages(Request $request): JsonResponse
    {
        $client = $this->portalClient($request);
        $user = $request->user();

        $threads = \App\Models\ChatThread::query()
            ->where('company_id', $client->company_id)
            ->where(function ($q) use ($user, $client) {
                $q->where(function ($owned) use ($client) {
                    $owned->where('type', \App\Models\ChatThread::TYPE_CLIENT)
                        ->where('subject_id', $client->id);
                })->orWhereHas('participants', fn ($p) => $p->where('users.id', $user->id));
            })
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
        $this->portalClient($request);
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
}
