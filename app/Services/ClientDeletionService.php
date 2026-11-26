<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientContract;
use App\Models\ClientFeedback;
use App\Models\ClientNote;
use App\Models\Company;
use App\Models\Project;
use App\Models\ProjectStudy;
use App\Models\ProjectTask;
use App\Models\Proposal;
use App\Models\WorkspaceCalendarEvent;
use App\Services\GoogleCalendarSyncService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Soft-delete a client and related workspace data.
 * Blocked when the client has invoices.
 */
class ClientDeletionService
{
    public function canDelete(Client $client): bool
    {
        return ! $client->invoices()->exists();
    }

    /**
     * @throws RuntimeException when the client has invoices
     */
    public function delete(Client $client): void
    {
        if (! $this->canDelete($client)) {
            throw new RuntimeException(__('This client has invoices and cannot be deleted.'));
        }

        $companyId = (string) $client->company_id;
        $clientId = (string) $client->id;

        DB::transaction(function () use ($companyId, $clientId, $client): void {
            $projectIds = Project::query()
                ->withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('client_id', $clientId)
                ->pluck('id');

            if ($projectIds->isNotEmpty()) {
                ProjectTask::query()
                    ->withoutGlobalScopes()
                    ->where('company_id', $companyId)
                    ->whereIn('project_id', $projectIds)
                    ->delete();

                Project::query()
                    ->withoutGlobalScopes()
                    ->where('company_id', $companyId)
                    ->whereIn('id', $projectIds)
                    ->get()
                    ->each(fn (Project $project) => $project->delete());
            }

            ProjectStudy::query()
                ->withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where(function ($q) use ($clientId, $projectIds): void {
                    $q->where('client_id', $clientId);
                    if ($projectIds->isNotEmpty()) {
                        $q->orWhereIn('project_id', $projectIds);
                    }
                })
                ->get()
                ->each(fn (ProjectStudy $study) => $study->delete());

            Proposal::query()
                ->withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('client_id', $clientId)
                ->get()
                ->each(fn (Proposal $proposal) => $proposal->delete());

            ClientContract::query()
                ->withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('client_id', $clientId)
                ->get()
                ->each(fn (ClientContract $contract) => $contract->delete());

            ClientNote::query()
                ->withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('client_id', $clientId)
                ->delete();

            ClientFeedback::query()
                ->withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('client_id', $clientId)
                ->delete();

            WorkspaceCalendarEvent::query()
                ->withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('client_id', $clientId)
                ->get()
                ->each(function (WorkspaceCalendarEvent $event) use ($companyId): void {
                    if (filled($event->google_calendar_event_id)) {
                        $company = Company::query()->find($companyId);
                        if ($company) {
                            app(GoogleCalendarSyncService::class)
                                ->deleteEventForCompany($company, (string) $event->google_calendar_event_id);
                        }
                    }
                    $event->delete();
                });

            if (\Illuminate\Support\Facades\Schema::hasTable('client_bookings')) {
                \App\Models\ClientBooking::query()
                    ->withoutGlobalScopes()
                    ->where('company_id', $companyId)
                    ->where('client_id', $clientId)
                    ->get()
                    ->each(fn (\App\Models\ClientBooking $booking) => $booking->delete());
            }
            if (\Illuminate\Support\Facades\Schema::hasTable('client_campaigns')) {
                \App\Models\ClientCampaign::query()
                    ->withoutGlobalScopes()
                    ->where('company_id', $companyId)
                    ->where('client_id', $clientId)
                    ->get()
                    ->each(fn (\App\Models\ClientCampaign $campaign) => $campaign->delete());
            }
            if (\Illuminate\Support\Facades\Schema::hasTable('client_prospects')) {
                \App\Models\ClientProspect::query()
                    ->withoutGlobalScopes()
                    ->where('company_id', $companyId)
                    ->where('client_id', $clientId)
                    ->get()
                    ->each(fn (\App\Models\ClientProspect $prospect) => $prospect->delete());
            }

            $client->delete();
        });
    }
}
