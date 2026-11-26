<?php

namespace App\Http\Controllers\Api\Workspace;

use App\Http\Controllers\Api\Concerns\ResolvesApiWorkspace;
use App\Http\Controllers\Controller;
use App\Services\WorkspaceApiImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImportApiController extends Controller
{
    use ResolvesApiWorkspace;

    public function __invoke(Request $request, WorkspaceApiImportService $import): JsonResponse
    {
        $company = $this->apiCompany();

        $data = $request->validate([
            'clients' => ['nullable', 'array', 'max:100'],
            'clients.*.name' => ['required', 'string', 'max:255'],
            'clients.*.email' => ['nullable', 'string', 'email', 'max:255'],
            'clients.*.phone' => ['nullable', 'string', 'max:64'],
            'clients.*.ref' => ['nullable', 'string', 'max:64'],
            'projects' => ['nullable', 'array', 'max:100'],
            'projects.*.title' => ['required', 'string', 'max:255'],
            'projects.*.description' => ['nullable', 'string', 'max:50000'],
            'projects.*.client_id' => ['nullable', 'string'],
            'projects.*.client_ref' => ['nullable', 'string', 'max:64'],
            'projects.*.status' => ['nullable', 'string'],
            'proposals' => ['nullable', 'array', 'max:50'],
            'proposals.*.name' => ['required', 'string', 'max:255'],
            'proposals.*.client_id' => ['nullable', 'string'],
            'proposals.*.client_ref' => ['nullable', 'string', 'max:64'],
            'proposals.*.currency' => ['nullable', 'string', 'size:3'],
            'proposals.*.items' => ['required', 'array', 'min:1'],
            'proposals.*.items.*.title' => ['nullable', 'string', 'max:255'],
            'proposals.*.items.*.description' => ['nullable', 'required_without:proposals.*.items.*.title', 'string', 'max:5000'],
            'proposals.*.items.*.quantity' => ['required', 'integer', 'min:1'],
            'proposals.*.items.*.unit_amount' => ['required', 'integer', 'min:0'],
            'products' => ['nullable', 'array', 'max:100'],
            'products.*.name' => ['required', 'string', 'max:255'],
            'products.*.sku' => ['nullable', 'string', 'max:64'],
            'products.*.price' => ['nullable', 'numeric', 'min:0'],
            'products.*.price_minor' => ['nullable', 'integer', 'min:0'],
            'products.*.currency' => ['nullable', 'string', 'size:3'],
            'products.*.stock_qty' => ['nullable', 'integer'],
            'products.*.is_service' => ['nullable', 'boolean'],
            'products.*.description' => ['nullable', 'string'],
        ]);

        if (($data['clients'] ?? []) === [] && ($data['projects'] ?? []) === [] && ($data['proposals'] ?? []) === [] && ($data['products'] ?? []) === []) {
            return response()->json(['message' => __('Provide at least one client or project to import.')], 422);
        }

        $result = $import->import($company, $data);

        return response()->json($result, 201);
    }
}
