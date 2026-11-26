<?php

namespace App\Http\Controllers\RealEstate;

use App\Enums\RealEstateContractType;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\RealEstateContract;
use App\Models\RealEstateOffer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ContractController extends Controller
{
    public function index(Request $request): View
    {
        $this->guard($request);

        return view('real-estate.contracts.index', [
            'contracts' => RealEstateContract::query()->with(['offer', 'client'])->latest()->paginate(20),
        ]);
    }

    public function create(Request $request): View
    {
        $this->guard($request);

        return view('real-estate.contracts.create', [
            'offers' => RealEstateOffer::query()->orderBy('title')->limit(200)->get(),
            'clients' => Client::query()->orderBy('name')->limit(200)->get(),
            'contractTypes' => RealEstateContractType::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $company = $this->guard($request);
        $data = $request->validate([
            'real_estate_offer_id' => ['nullable', 'string'],
            'client_id' => ['nullable', 'string'],
            'contract_type' => ['required', Rule::enum(RealEstateContractType::class)],
            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'status' => ['nullable', 'string', 'max:32'],
            'notes' => ['nullable', 'string'],
            'document' => ['nullable', 'file', 'max:10240'],
        ]);

        $path = null;
        if ($request->hasFile('document')) {
            $path = $request->file('document')->store('real-estate/'.$company->id.'/contracts', 'public');
        }

        RealEstateContract::query()->create([
            'company_id' => $company->id,
            'real_estate_offer_id' => $data['real_estate_offer_id'] ?? null,
            'client_id' => $data['client_id'] ?? null,
            'contract_type' => $data['contract_type'],
            'amount_minor' => flowdesk_decimal_to_minor((string) $data['amount'], $data['currency']),
            'currency' => strtoupper($data['currency']),
            'starts_on' => $data['starts_on'] ?? null,
            'ends_on' => $data['ends_on'] ?? null,
            'status' => $data['status'] ?? 'draft',
            'notes' => $data['notes'] ?? null,
            'document_path' => $path,
        ]);

        return redirect()->route('real-estate.contracts.index')->with('status', __('Contract created.'));
    }

    private function guard(Request $request)
    {
        $user = $request->user();
        abort_if(! $user?->can('workspace.manage_real_estate') && ! $user?->can('workspace.manage_clients'), 403);
        abort_if(! $user->company, 403);

        return $user->company;
    }
}
