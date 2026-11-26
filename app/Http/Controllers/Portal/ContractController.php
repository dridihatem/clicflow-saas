<?php

namespace App\Http\Controllers\Portal;

use App\Enums\ClientContractStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Concerns\ResolvesPortalClient;
use App\Models\Client;
use App\Models\ClientContract;
use App\Services\ContractMergeService;
use App\Services\ContractPdfService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContractController extends Controller
{
    use ResolvesPortalClient;

    public function index(Request $request): View
    {
        $client = $this->portalClient($request);

        $contracts = ClientContract::query()
            ->withoutGlobalScopes()
            ->where('company_id', $client->company_id)
            ->where('client_id', $client->id)
            ->whereIn('status', [ClientContractStatus::Sent->value, ClientContractStatus::Signed->value])
            ->latest()
            ->paginate(20);

        return view('portal.contracts.index', compact('contracts'));
    }

    public function show(Request $request, ClientContract $contract, ContractMergeService $merge): View
    {
        $client = $this->portalClient($request);
        $this->authorizePortalContract($client, $contract);
        $contract->load('company');

        $bodyHtml = $merge->interpolate((string) $contract->body_html, $contract);
        $canSign = $contract->status === ClientContractStatus::Sent;

        return view('portal.contracts.show', compact('contract', 'bodyHtml', 'canSign'));
    }

    public function sign(Request $request, ClientContract $contract): RedirectResponse
    {
        $client = $this->portalClient($request);
        $this->authorizePortalContract($client, $contract);
        abort_if($contract->status !== ClientContractStatus::Sent, 422);

        $validated = $request->validate([
            'accept' => ['accepted'],
            'signature_data' => ['nullable', 'string', 'max:700000', 'regex:/^data:image\/png;base64,/'],
            'signature_file' => ['nullable', 'file', 'mimes:png,jpg,jpeg', 'max:512'],
        ]);

        $hasCanvas = filled($validated['signature_data'] ?? null);
        $hasUpload = $request->hasFile('signature_file');
        abort_if(! $hasCanvas && ! $hasUpload, 422, __('contract_signature_required'));

        $path = $this->storeSignature($contract, $validated['signature_data'] ?? null, $request->file('signature_file'));

        if ($contract->client_signature_path && $contract->client_signature_path !== $path) {
            Storage::disk('public')->delete($contract->client_signature_path);
        }

        $contract->update([
            'client_signature_path' => $path,
            'client_signed_at' => now(),
            'status' => ClientContractStatus::Signed,
        ]);

        return redirect()
            ->route('portal.contracts.show', $contract)
            ->with('status', __('contract_signed_success'));
    }

    public function pdf(Request $request, ClientContract $contract, ContractPdfService $pdfs): StreamedResponse
    {
        $client = $this->portalClient($request);
        $this->authorizePortalContract($client, $contract);

        return $pdfs->stream($contract);
    }

    private function authorizePortalContract(Client $client, ClientContract $contract): void
    {
        abort_if((string) $contract->company_id !== (string) $client->company_id, 404);
        abort_if((string) $contract->client_id !== (string) $client->id, 404);
        abort_unless($contract->isVisibleInPortal(), 404);
    }

    private function storeSignature(ClientContract $contract, ?string $dataUrl, ?UploadedFile $file): string
    {
        $dir = 'tenants/'.$contract->company_id.'/contracts';
        $filename = $contract->id.'.png';

        if ($file instanceof UploadedFile) {
            $ext = strtolower($file->getClientOriginalExtension() ?: 'png');
            $ext = in_array($ext, ['png', 'jpg', 'jpeg'], true) ? $ext : 'png';
            $filename = $contract->id.'.'.$ext;

            return $file->storeAs($dir, $filename, 'public');
        }

        $raw = (string) preg_replace('#^data:image/png;base64,#', '', (string) $dataUrl);
        $binary = base64_decode($raw, true);
        abort_if($binary === false || $binary === '', 422, __('contract_signature_invalid'));
        abort_if(strlen($binary) > 524288, 422, __('contract_signature_too_large'));

        $path = $dir.'/'.$filename;
        Storage::disk('public')->put($path, $binary);

        return $path;
    }
}
