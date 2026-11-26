<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Proposal;
use App\Models\ProposalItem;
use App\Services\InvoicePdfService;
use App\Services\InvoiceTotalsService;
use App\Services\ProposalPdfService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * « Aperçu PDF » from the invoice / quote editor: renders the unsaved form as a PDF (nothing is stored).
 */
class DocumentDraftPreviewController extends Controller
{
    public function invoice(Request $request, InvoiceTotalsService $totals, InvoicePdfService $pdf): Response
    {
        $company = $this->company($request);
        [$items, $taxes, $currency] = $this->lines($request, $company, $totals);

        $invoice = new Invoice;
        $invoice->forceFill([
            'company_id' => $company->id,
            'client_id' => $this->clientId($request, $company),
            'number' => filled($request->input('number')) ? (string) $request->input('number') : __('document_preview_number'),
            'status' => 'draft',
            'subtotal_amount' => $taxes['subtotal'],
            'discount_type' => $taxes['discount_type'],
            'discount_value' => $taxes['discount_value'],
            'discount_amount' => $taxes['discount'],
            'vat_amount' => $taxes['vat'],
            'fiscal_stamp_amount' => $taxes['stamp'],
            'amount' => $taxes['total'],
            'currency' => $currency,
            'due_date' => $this->date($request->input('due_date')),
            'customer_notes' => $request->input('customer_notes'),
        ]);
        $invoice->created_at = now();
        $invoice->setRelation('company', $company);
        $invoice->setRelation('client', $this->client($request, $company));
        $invoice->setRelation('items', collect($items)->map(fn (array $row) => (new InvoiceItem)->forceFill($row)));
        $invoice->setRelation('payments', collect());

        return $this->inline(Pdf::loadHTML($pdf->html($invoice))->output(), 'apercu-facture.pdf');
    }

    public function proposal(Request $request, InvoiceTotalsService $totals, ProposalPdfService $pdf): Response
    {
        $company = $this->company($request);
        [$items, $taxes, $currency] = $this->lines($request, $company, $totals);

        $proposal = new Proposal;
        $proposal->forceFill([
            'company_id' => $company->id,
            'client_id' => $this->clientId($request, $company),
            'name' => (string) $request->input('name', ''),
            'number' => __('document_preview_number'),
            'subtotal_amount' => $taxes['subtotal'],
            'discount_type' => $taxes['discount_type'],
            'discount_value' => $taxes['discount_value'],
            'discount_amount' => $taxes['discount'],
            'vat_amount' => $taxes['vat'],
            'fiscal_stamp_amount' => $taxes['stamp'],
            'amount' => $taxes['total'],
            'currency' => $currency,
            'valid_until' => $this->date($request->input('valid_until')),
        ]);
        $proposal->created_at = now();
        $proposal->setRelation('company', $company);
        $proposal->setRelation('client', $this->client($request, $company));
        $proposal->setRelation('items', collect($items)->map(fn (array $row) => (new ProposalItem)->forceFill($row)));

        return $this->inline(Pdf::loadHTML($pdf->html($proposal))->output(), 'apercu-devis.pdf');
    }

    private function company(Request $request): Company
    {
        $user = $request->user();
        abort_if(! $user || ! $user->company, 403);
        abort_if(! $user->hasAnyRole(['company_admin', 'team_member']), 403);

        return $user->company;
    }

    /**
     * @return array{0: list<array<string, mixed>>, 1: array<string, mixed>, 2: string}
     */
    private function lines(Request $request, Company $company, InvoiceTotalsService $totals): array
    {
        $data = $request->validate([
            'currency' => ['nullable', 'string', 'size:3'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.title' => ['nullable', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string', 'max:5000'],
            'items.*.quantity' => ['nullable', 'integer', 'min:0'],
            'items.*.unit_amount' => ['nullable', 'integer', 'min:0'],
            'items.*.unit' => ['nullable', 'string', 'max:32'],
            'items.*.vat_rate' => InvoiceTotalsService::lineRateRule(),
            'discount_type' => InvoiceTotalsService::discountRules()['discount_type'],
            'discount_value' => InvoiceTotalsService::discountRules()['discount_value'],
        ]);
        $currency = strtoupper((string) ($data['currency'] ?? $company->default_currency ?? flowdesk_default_currency()));
        $defaultVat = $totals->companyVatPercent($company);
        $items = [];
        foreach ($data['items'] as $i => $row) {
            $qty = max(0, (int) ($row['quantity'] ?? 1));
            $unit = max(0, (int) ($row['unit_amount'] ?? 0));
            $items[] = [
                'title' => flowdesk_line_title($row['title'] ?? null),
                'description' => (string) ($row['description'] ?? ''),
                'unit' => $row['unit'] ?? null,
                'quantity' => $qty,
                'unit_amount' => $unit,
                'total_amount' => $qty * $unit,
                'vat_rate' => InvoiceTotalsService::normalizeRate($row['vat_rate'] ?? null, $defaultVat),
                'sort_order' => $i,
            ];
        }
        $rows = array_map(fn (array $r) => ['total' => $r['total_amount'], 'vat_rate' => $r['vat_rate']], $items);
        $taxes = $totals->fromItemsMinor($rows, $company, $data['discount_type'] ?? null, $data['discount_value'] ?? null, $currency);

        return [$items, $taxes, $currency];
    }

    private function clientId(Request $request, Company $company): ?string
    {
        $client = $this->client($request, $company);

        return $client?->exists ? (string) $client->id : null;
    }

    private function client(Request $request, Company $company): ?Client
    {
        if ($request->input('client_mode') === 'new' && filled($request->input('new_client_name'))) {
            return (new Client)->forceFill([
                'company_id' => $company->id,
                'name' => (string) $request->input('new_client_name'),
                'email' => $request->input('new_client_email'),
                'phone' => $request->input('new_client_phone'),
            ]);
        }
        $id = $request->input('client_id');
        if (! filled($id)) {
            return null;
        }

        return Client::query()->withoutGlobalScopes()->where('company_id', $company->id)->whereNull('deleted_at')->find($id);
    }

    private function date(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
    }

    private function inline(string $pdf, string $filename): Response
    {
        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Cache-Control' => 'no-store',
        ]);
    }
}
