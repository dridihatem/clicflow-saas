<?php

namespace App\Http\Controllers\RealEstate;

use App\Enums\RealEstatePropertyType;
use App\Enums\RealEstateTransactionType;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\RealEstateOffer;
use App\Models\RealEstateOfferImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OfferController extends Controller
{
    public function index(Request $request): View
    {
        $this->guard($request);
        $offers = RealEstateOffer::query()
            ->with('client')
            ->when($request->query('property_type'), fn ($q, $v) => $q->where('property_type', $request->query('property_type')))
            ->when($request->query('transaction_type'), fn ($q) => $q->where('transaction_type', $request->query('transaction_type')))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('real-estate.offers.index', [
            'offers' => $offers,
            'propertyTypes' => RealEstatePropertyType::cases(),
            'transactionTypes' => RealEstateTransactionType::cases(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->guard($request);

        return view('real-estate.offers.create', [
            'clients' => Client::query()->orderBy('name')->limit(200)->get(),
            'propertyTypes' => RealEstatePropertyType::cases(),
            'transactionTypes' => RealEstateTransactionType::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $company = $this->guard($request);
        $data = $this->validated($request);
        $offer = RealEstateOffer::query()->create(array_merge($data, [
            'company_id' => $company->id,
            'price_minor' => flowdesk_decimal_to_minor((string) $request->input('price'), $data['currency']),
            'status' => $data['status'] ?? 'draft',
        ]));

        return redirect()->route('real-estate.offers.show', $offer)->with('status', __('Offer created.'));
    }

    public function show(Request $request, RealEstateOffer $offer): View
    {
        $this->guard($request);
        abort_if((string) $offer->company_id !== (string) $request->user()->company_id, 404);
        $offer->load(['images', 'client', 'contracts']);

        return view('real-estate.offers.show', compact('offer'));
    }

    public function update(Request $request, RealEstateOffer $offer): RedirectResponse
    {
        $company = $this->guard($request);
        abort_if((string) $offer->company_id !== (string) $company->id, 404);
        $data = $this->validated($request);
        $offer->update(array_merge($data, [
            'price_minor' => flowdesk_decimal_to_minor((string) $request->input('price'), $data['currency']),
        ]));

        return back()->with('status', __('Offer updated.'));
    }

    public function storeImage(Request $request, RealEstateOffer $offer): RedirectResponse
    {
        $company = $this->guard($request);
        abort_if((string) $offer->company_id !== (string) $company->id, 404);
        $data = $request->validate(['image' => ['required', 'image', 'max:5120']]);
        $path = $data['image']->store('real-estate/'.$company->id.'/'.$offer->id, 'public');
        RealEstateOfferImage::query()->create([
            'company_id' => $company->id,
            'real_estate_offer_id' => $offer->id,
            'path' => $path,
            'sort_order' => ((int) $offer->images()->max('sort_order')) + 1,
        ]);

        return back()->with('status', __('Image uploaded.'));
    }

    public function destroyImage(Request $request, RealEstateOfferImage $image): RedirectResponse
    {
        $this->guard($request);
        abort_if((string) $image->company_id !== (string) $request->user()->company_id, 404);
        Storage::disk('public')->delete($image->path);
        $image->delete();

        return back()->with('status', __('Image removed.'));
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'property_type' => ['required', Rule::enum(RealEstatePropertyType::class)],
            'transaction_type' => ['required', Rule::enum(RealEstateTransactionType::class)],
            'price' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string', 'max:32'],
            'description' => ['nullable', 'string'],
            'client_id' => ['nullable', 'string'],
        ]);
        unset($data['price']);

        return $data;
    }

    private function guard(Request $request)
    {
        $user = $request->user();
        abort_if(! $user?->can('workspace.manage_real_estate') && ! $user?->can('workspace.manage_clients'), 403);
        abort_if(! $user->company, 403);

        return $user->company;
    }
}
