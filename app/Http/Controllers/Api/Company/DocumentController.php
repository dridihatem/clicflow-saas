<?php

namespace App\Http\Controllers\Api\Company;

use App\Enums\InvoiceStatus;
use App\Enums\ProposalStatus;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientContract;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Proposal;
use App\Models\ProposalItem;
use App\Models\WorkspaceCalendarEvent;
use App\Services\InvoiceMailService;
use App\Services\InvoicePdfService;
use App\Services\InvoiceReferenceService;
use App\Services\InvoiceTotalsService;
use App\Services\ProposalMailService;
use App\Services\ProposalPdfService;
use App\Services\ProposalReferenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function storeClientInvoice(
        Request $request,
        string $client,
        InvoiceTotalsService $totalsService,
        InvoiceReferenceService $referenceService,
        InvoiceMailService $mailer,
    ): JsonResponse {
        $company = $request->user()->company;
        abort_if(! $company, 403);
        $model = $this->findClient($company->id, $client);

        $data = $request->validate([
            'description' => ['required', 'string', 'max:500'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'due_date' => ['nullable', 'date'],
            'currency' => ['nullable', 'string', 'size:3'],
            'customer_notes' => ['nullable', 'string', 'max:5000'],
            'send' => ['sometimes', 'boolean'],
        ]);

        $currency = strtoupper((string) ($data['currency'] ?? $company->default_currency ?? flowdesk_default_currency()));
        $quantity = max(1, (int) ($data['quantity'] ?? 1));
        $unitMinor = flowdesk_decimal_to_minor((string) $data['amount'], $currency);
        if ($unitMinor === null || $unitMinor < 1) {
            return response()->json(['message' => __('Invalid amount.')], 422);
        }

        $subtotal = $quantity * $unitMinor;
        $taxes = $totalsService->fromSubtotalMinor($subtotal, $company);

        $invoice = DB::transaction(function () use ($company, $model, $data, $currency, $quantity, $unitMinor, $taxes, $referenceService) {
            $inv = Invoice::query()->withoutGlobalScope('tenant')->create([
                'company_id' => $company->id,
                'client_id' => $model->id,
                'proposal_id' => null,
                'project_id' => null,
                'number' => null,
                'status' => InvoiceStatus::Draft,
                'subtotal_amount' => $taxes['subtotal'],
                'vat_amount' => $taxes['vat'],
                'fiscal_stamp_amount' => $taxes['stamp'],
                'amount' => $taxes['total'],
                'currency' => $currency,
                'due_date' => $data['due_date'] ?? null,
                'customer_notes' => $data['customer_notes'] ?? null,
            ]);

            InvoiceItem::query()->withoutGlobalScopes()->create([
                'company_id' => $company->id,
                'invoice_id' => $inv->id,
                'description' => $data['description'],
                'quantity' => $quantity,
                'unit_amount' => $unitMinor,
                'total_amount' => $quantity * $unitMinor,
            ]);

            $referenceService->assignNextNumber($inv, $company);

            return $inv->refresh();
        });

        $sent = false;
        if ($request->boolean('send')) {
            try {
                $mailer->send($invoice->load('client'), $company);
                $invoice->update(['status' => InvoiceStatus::Sent]);
                $sent = true;
            } catch (\Throwable $e) {
                return response()->json([
                    'invoice' => $this->invoicePayload($invoice->fresh()),
                    'sent' => false,
                    'message' => $e->getMessage(),
                ], 201);
            }
        }

        return response()->json([
            'invoice' => $this->invoicePayload($invoice->fresh()),
            'sent' => $sent,
            'message' => $sent ? __('Invoice created and emailed.') : __('Invoice created.'),
        ], 201);
    }

    public function storeClientProposal(
        Request $request,
        string $client,
        InvoiceTotalsService $totalsService,
        ProposalReferenceService $referenceService,
        ProposalMailService $mailer,
    ): JsonResponse {
        $company = $request->user()->company;
        abort_if(! $company, 403);
        $model = $this->findClient($company->id, $client);

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:500'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'valid_until' => ['nullable', 'date'],
            'currency' => ['nullable', 'string', 'size:3'],
            'customer_notes' => ['nullable', 'string', 'max:5000'],
            'send' => ['sometimes', 'boolean'],
        ]);

        $currency = strtoupper((string) ($data['currency'] ?? $company->default_currency ?? flowdesk_default_currency()));
        $quantity = max(1, (int) ($data['quantity'] ?? 1));
        $unitMinor = flowdesk_decimal_to_minor((string) $data['amount'], $currency);
        if ($unitMinor === null || $unitMinor < 1) {
            return response()->json(['message' => __('Invalid amount.')], 422);
        }

        $subtotal = $quantity * $unitMinor;
        $taxes = $totalsService->fromSubtotalMinor($subtotal, $company);
        $name = trim((string) ($data['name'] ?? '')) ?: __('Quote for :client', ['client' => $model->name]);

        $proposal = DB::transaction(function () use ($company, $model, $data, $currency, $quantity, $unitMinor, $taxes, $referenceService, $name) {
            $proposal = Proposal::query()->withoutGlobalScopes()->create([
                'company_id' => $company->id,
                'client_id' => $model->id,
                'project_id' => null,
                'provider_id' => null,
                'name' => $name,
                'number' => null,
                'status' => ProposalStatus::Draft,
                'subtotal_amount' => $taxes['subtotal'],
                'vat_amount' => $taxes['vat'],
                'fiscal_stamp_amount' => $taxes['stamp'],
                'amount' => $taxes['total'],
                'currency' => $currency,
                'valid_until' => $data['valid_until'] ?? null,
                'customer_notes' => $data['customer_notes'] ?? null,
            ]);

            ProposalItem::query()->withoutGlobalScopes()->create([
                'company_id' => $company->id,
                'proposal_id' => $proposal->id,
                'description' => $data['description'],
                'quantity' => $quantity,
                'unit_amount' => $unitMinor,
                'total_amount' => $quantity * $unitMinor,
            ]);

            $referenceService->assignNextNumber($proposal, $company);

            return $proposal->refresh();
        });

        $sent = false;
        if ($request->boolean('send')) {
            try {
                $mailer->send($proposal->load('client'), $company);
                $proposal->update(['status' => ProposalStatus::Sent]);
                $sent = true;
            } catch (\Throwable $e) {
                return response()->json([
                    'proposal' => $this->proposalPayload($proposal->fresh()),
                    'sent' => false,
                    'message' => $e->getMessage(),
                ], 201);
            }
        }

        return response()->json([
            'proposal' => $this->proposalPayload($proposal->fresh()),
            'sent' => $sent,
            'message' => $sent ? __('Quote created and emailed.') : __('Quote created.'),
        ], 201);
    }

    public function storeClientReminder(Request $request, string $client): JsonResponse
    {
        $company = $request->user()->company;
        abort_if(! $company, 403);
        $model = $this->findClient($company->id, $client);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['nullable', 'date_format:H:i'],
        ]);

        $event = WorkspaceCalendarEvent::query()->withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'client_id' => $model->id,
            'created_by' => $request->user()->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'starts_on' => $data['date'],
            'start_time' => $data['time'] ?? null,
            'ends_on' => $data['date'],
            'kind' => 'reminder',
            'source_type' => 'mobile_app',
        ]);

        return response()->json([
            'reminder' => [
                'id' => $event->id,
                'title' => $event->title,
                'description' => $event->description,
                'date' => optional($event->starts_on)?->toDateString(),
                'time' => $event->start_time,
                'kind' => 'reminder',
            ],
            'message' => __('Reminder saved.'),
        ], 201);
    }

    public function storeClientMeeting(Request $request, string $client): JsonResponse
    {
        $company = $request->user()->company;
        abort_if(! $company, 403);
        $model = $this->findClient($company->id, $client);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['nullable', 'date_format:H:i'],
        ]);

        $event = WorkspaceCalendarEvent::query()->withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'client_id' => $model->id,
            'created_by' => $request->user()->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'starts_on' => $data['date'],
            'start_time' => $data['time'] ?? null,
            'ends_on' => $data['date'],
            'kind' => 'meeting',
            'source_type' => 'mobile_app',
        ]);

        return response()->json([
            'meeting' => [
                'id' => $event->id,
                'title' => $event->title,
                'description' => $event->description,
                'date' => optional($event->starts_on)?->toDateString(),
                'time' => $event->start_time,
                'kind' => 'meeting',
            ],
            'message' => __('Meeting scheduled.'),
        ], 201);
    }

    public function sendInvoice(Request $request, string $invoice, InvoiceMailService $mailer): JsonResponse
    {
        $company = $request->user()->company;
        abort_if(! $company, 403);
        $model = $this->findInvoice($company->id, $invoice);

        try {
            $mailer->send($model->load('client'), $company);
            if ($model->status === InvoiceStatus::Draft) {
                $model->update(['status' => InvoiceStatus::Sent]);
            }
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'invoice' => $this->invoicePayload($model->fresh()),
            'message' => __('Invoice email queued for delivery.'),
        ]);
    }

    public function invoicePdf(Request $request, string $invoice, InvoicePdfService $pdfs): StreamedResponse|JsonResponse
    {
        $company = $request->user()->company;
        abort_if(! $company, 403);
        $model = $this->findInvoice($company->id, $invoice);

        $filename = 'invoice-'.($model->number ?? $model->id).'.pdf';

        if ($request->boolean('base64') || $request->query('format') === 'base64') {
            return response()->json([
                'filename' => $filename,
                'content_base64' => base64_encode($pdfs->output($model)),
                'mime' => 'application/pdf',
            ]);
        }

        return response()->streamDownload(function () use ($model, $pdfs): void {
            echo $pdfs->output($model);
        }, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function sendProposal(Request $request, string $proposal, ProposalMailService $mailer): JsonResponse
    {
        $company = $request->user()->company;
        abort_if(! $company, 403);
        $model = $this->findProposal($company->id, $proposal);

        try {
            $mailer->send($model->load('client'), $company);
            if ($model->status === ProposalStatus::Draft) {
                $model->update(['status' => ProposalStatus::Sent]);
            }
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'proposal' => $this->proposalPayload($model->fresh()),
            'message' => __('Quote email queued for delivery.'),
        ]);
    }

    public function proposalPdf(Request $request, string $proposal, ProposalPdfService $pdfs): StreamedResponse|JsonResponse
    {
        $company = $request->user()->company;
        abort_if(! $company, 403);
        $model = $this->findProposal($company->id, $proposal);

        $filename = 'quote-'.($model->number ?? $model->id).'.pdf';

        if ($request->boolean('base64') || $request->query('format') === 'base64') {
            return response()->json([
                'filename' => $filename,
                'content_base64' => base64_encode($pdfs->output($model)),
                'mime' => 'application/pdf',
            ]);
        }

        return response()->streamDownload(function () use ($model, $pdfs): void {
            echo $pdfs->output($model);
        }, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    private function findClient(string $companyId, string $clientId): Client
    {
        return Client::query()
            ->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereKey($clientId)
            ->firstOrFail();
    }

    private function findInvoice(string $companyId, string $invoiceId): Invoice
    {
        return Invoice::query()
            ->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereKey($invoiceId)
            ->firstOrFail();
    }

    private function findProposal(string $companyId, string $proposalId): Proposal
    {
        return Proposal::query()
            ->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereKey($proposalId)
            ->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function invoicePayload(Invoice $invoice): array
    {
        $currency = function_exists('flowdesk_invoice_currency')
            ? flowdesk_invoice_currency($invoice)
            : (string) ($invoice->currency ?? '');
        $amount = function_exists('flowdesk_format_minor')
            ? flowdesk_format_minor((int) $invoice->amount, $currency).' '.$currency
            : ((string) $invoice->amount).' '.$currency;
        $status = $invoice->status instanceof \BackedEnum
            ? $invoice->status->value
            : (string) ($invoice->status ?? '');

        return [
            'id' => $invoice->id,
            'reference' => $invoice->number ?: $invoice->id,
            'amount' => $amount,
            'due_date' => optional($invoice->due_date)?->toDateString(),
            'status' => $status,
            'currency' => $currency,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function proposalPayload(Proposal $proposal): array
    {
        $currency = (string) ($proposal->currency ?? '');
        $amount = function_exists('flowdesk_format_minor')
            ? flowdesk_format_minor((int) $proposal->amount, $currency).' '.$currency
            : ((string) $proposal->amount).' '.$currency;
        $status = $proposal->status instanceof \BackedEnum
            ? $proposal->status->value
            : (string) ($proposal->status ?? '');

        return [
            'id' => $proposal->id,
            'reference' => $proposal->number ?: $proposal->id,
            'name' => $proposal->name,
            'amount' => $amount,
            'valid_until' => optional($proposal->valid_until)?->toDateString(),
            'status' => $status,
            'currency' => $currency,
        ];
    }
}
