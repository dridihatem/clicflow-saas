<?php

namespace App\Http\Controllers\Mcp;

use App\Http\Controllers\Controller;
use App\Models\McpToken;
use App\Services\Mcp\McpServer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CompanyMcpController extends Controller
{
    public function handle(Request $request, McpServer $server): JsonResponse|StreamedResponse|Response
    {
        return $server->handle($request, McpToken::AUDIENCE_COMPANY);
    }
}
