<?php

namespace App\Http\Controllers\RealEstate;

use App\Enums\RealEstatePropertyType;
use App\Enums\RealEstateTransactionType;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\RealEstateDemand;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DemandController extends Controller
{
    public function index(Request $request): View
    {
        $this->guard($request);

        return view('real-estate.demands.index', [
            'demands' => RealEstateDemand::query()->with('client')->latest()->paginate(20),
        ]);
    }

    public function create(Request $request): View
    {
        $this->guard($request);

        return view('real-estate.demands.create', [
            'clients' => Client::query()->orderBy('name')->limit(200)->get(),
            'propertyTypes' => RealEstatePropertyType::cases(),
            'transactionTypes' => RealEstateTransactionType::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $company = $this->guard($request);
        $data = $request->validate([
            'client_id' => ['nullable', 'string'],
            'property_types' => ['nullable', 'array'],
            'property_types.*' => ['string'],
            'transaction_types' => ['nullable', 'array'],
            'transaction_types.*' => ['string'],
            'budget_min' => ['nullable', 'numeric', 'min:0'],
            'budget_max' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'notes' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'max:32'],
        ]);

        RealEstateDemand::query()->create([
            'company_id' => $company->id,
            'client_id' => $data['client_id'] ?? null,
            'property_types' => $data['property_types'] ?? [],
            'transaction_types' => $data['transaction_types'] ?? [],
            'budget_min_minor' => isset($data['budget_min'])
                ? flowdesk_decimal_to_minor((string) $data['budget_min'], $data['currency'])
                : null,
            'budget_max_minor' => isset($data['budget_max'])
                ? flowdesk_decimal_to_minor((string) $data['budget_max'], $data['currency'])
                : null,
            'currency' => strtoupper($data['currency']),
            'notes' => $data['notes'] ?? null,
            'status' => $data['status'] ?? 'open',
        ]);

        return redirect()->route('real-estate.demands.index')->with('status', __('Demand created.'));
    }

    private function guard(Request $request)
    {
        $user = $request->user();
        abort_if(! $user?->can('workspace.manage_real_estate') && ! $user?->can('workspace.manage_clients'), 403);
        abort_if(! $user->company, 403);

        return $user->company;
    }
}
