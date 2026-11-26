<?php

namespace App\Http\Controllers\Portal;

use App\Enums\ClientProspectStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Concerns\ResolvesPortalClient;
use App\Models\ClientCampaign;
use App\Models\ClientProspect;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CampaignController extends Controller
{
    use ResolvesPortalClient;

    public function index(Request $request): View
    {
        $client = $this->portalClient($request);
        $campaigns = ClientCampaign::query()
            ->where('client_id', $client->id)
            ->withCount('prospects')
            ->orderByDesc('starts_on')
            ->orderByDesc('created_at')
            ->get();

        $statusCounts = ClientProspect::query()
            ->where('client_id', $client->id)
            ->whereNotNull('campaign_id')
            ->selectRaw('campaign_id, status, count(*) as aggregate')
            ->groupBy('campaign_id', 'status')
            ->get()
            ->groupBy('campaign_id');

        return view('portal.campaigns.index', [
            'client' => $client,
            'campaigns' => $campaigns,
            'statusCounts' => $statusCounts,
            'statuses' => ClientProspectStatus::cases(),
        ]);
    }
}
