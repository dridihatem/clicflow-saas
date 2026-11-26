<?php

namespace App\Services\Mcp;

use App\Models\Company;
use App\Models\McpAuditLog;
use App\Models\McpToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class McpServer
{
    public const PROTOCOL = '2025-03-26';

    /** Protocol revisions this server speaks (echoed back when the client asks for one of them). */
    public const SUPPORTED_PROTOCOLS = ['2025-06-18', '2025-03-26', '2024-11-05'];

    public function __construct(
        private CompanyMcpTools $companyTools,
        private AdminMcpTools $adminTools,
    ) {}

    public function handle(Request $request, string $audience): JsonResponse|StreamedResponse|Response
    {
        if ($request->isMethod('GET')) {
            // Streamable HTTP: we do not offer a server-initiated SSE stream on GET.
            if (str_contains((string) $request->header('Accept', ''), 'text/event-stream')) {
                return response('', 405, ['Allow' => 'POST, DELETE']);
            }

            return response()->json([
                'name' => 'ClicFlow MCP',
                'protocol' => 'jsonrpc-2.0',
                'transport' => 'streamable-http',
                'protocolVersion' => self::PROTOCOL,
                'docs' => 'Use POST with Authorization: Bearer <token> and JSON-RPC methods initialize, tools/list, tools/call.',
            ]);
        }

        if ($request->isMethod('DELETE')) {
            return response()->noContent();
        }

        $plain = (string) $request->bearerToken();
        $token = McpToken::findActiveByPlain($plain);

        if (! $token || $token->tokenable_type !== $audience) {
            $this->audit($request, null, $audience, 'auth', null, 'unauthorized', 'Invalid or revoked token.');

            return $this->unauthorized($request, $audience, $plain !== '', 'Unauthorized: invalid or revoked MCP token.');
        }

        // OAuth tokens are bound to the MCP resource (host) they were issued for.
        if ($token->isOAuth() && $token->oauth_resource) {
            $here = McpOAuthMetadata::resource($request);
            if (rtrim((string) $token->oauth_resource, '/') !== $here) {
                $this->audit($request, $token, $audience, 'auth', null, 'unauthorized', 'Token audience mismatch.');

                return $this->unauthorized($request, $audience, true, 'Unauthorized: this token was issued for another MCP server.');
            }
        }

        if ($audience === McpToken::AUDIENCE_COMPANY) {
            $company = $token->company();
            $current = app()->bound('currentCompany') ? app('currentCompany') : null;
            if (! $company instanceof Company) {
                return $this->unauthorized($request, $audience, true, 'Unauthorized: workspace not found.');
            }
            if ($current instanceof Company && (string) $current->id !== (string) $company->id) {
                $this->audit($request, $token, $audience, 'auth', null, 'denied', 'Token does not match this workspace.');

                return $this->rpcHttpError(403, null, -32003, 'Forbidden: this token belongs to another workspace.');
            }
            app()->instance('currentCompany', $company);
        }

        $rateKey = 'mcp:'.$token->id;
        if (RateLimiter::tooManyAttempts($rateKey, 60)) {
            $this->audit($request, $token, $audience, 'rate_limit', null, 'denied', 'Rate limited.');

            return $this->rpcHttpError(429, null, -32000, 'Too many requests. Limit is 60 per minute.');
        }
        RateLimiter::hit($rateKey, 60);
        $token->touchLastUsed();

        $payload = $request->json()->all();
        if ($payload === [] && $request->getContent() !== '' && $request->getContent() !== '[]') {
            $decoded = json_decode($request->getContent(), true);
            if (! is_array($decoded)) {
                return $this->rpcHttpError(400, null, -32700, 'Parse error: invalid JSON.');
            }
            $payload = $decoded;
        }

        // Notifications (no id) — accept and return 202
        if (isset($payload['method']) && ! array_key_exists('id', $payload)) {
            return response()->noContent(202);
        }

        $result = $this->dispatch($request, $token, $audience, $payload);

        $wantsSse = str_contains((string) $request->header('Accept', ''), 'text/event-stream')
            && ! str_contains((string) $request->header('Accept', ''), 'application/json');

        if ($wantsSse) {
            $json = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return response()->stream(function () use ($json): void {
                echo "event: message\n";
                echo 'data: '.$json."\n\n";
            }, 200, [
                'Content-Type' => 'text/event-stream; charset=utf-8',
                'Cache-Control' => 'no-cache',
                'X-Accel-Buffering' => 'no',
            ]);
        }

        return response()->json($result);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function dispatch(Request $request, McpToken $token, string $audience, array $payload): array
    {
        $id = $payload['id'] ?? null;
        $method = isset($payload['method']) && is_string($payload['method']) ? $payload['method'] : '';
        $params = is_array($payload['params'] ?? null) ? $payload['params'] : [];

        if (($payload['jsonrpc'] ?? '') !== '2.0' || $method === '') {
            return $this->rpcError($id, -32600, 'Invalid Request.');
        }

        try {
            $value = match ($method) {
                'initialize' => $this->initialize($audience, $params),
                'ping' => (object) [],
                'tools/list' => ['tools' => $this->tools($audience)->list()],
                'tools/call' => $this->callTool($request, $token, $audience, $params),
                'notifications/initialized', 'notifications/cancelled' => (object) [],
                default => null,
            };

            if ($value === null) {
                $this->audit($request, $token, $audience, $method, null, 'error', 'Method not found.');

                return $this->rpcError($id, -32601, 'Method not found: '.$method);
            }

            if ($method === 'tools/call' && is_array($value) && ($value['__rpc_error'] ?? false)) {
                $this->audit($request, $token, $audience, $method, $params['name'] ?? null, $value['status'] ?? 'error', $value['message'] ?? '');

                return $this->rpcError($id, $value['code'] ?? -32602, $value['message'] ?? 'Tool error.');
            }

            $toolName = $method === 'tools/call' ? (string) ($params['name'] ?? '') : null;
            $this->audit($request, $token, $audience, $method, $toolName, 'ok', null);

            return ['jsonrpc' => '2.0', 'id' => $id, 'result' => $value];
        } catch (Throwable $e) {
            report($e);
            $this->audit($request, $token, $audience, $method, $params['name'] ?? null, 'error', $e->getMessage());

            return $this->rpcError($id, -32603, 'Internal error.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function initialize(string $audience, array $params = []): array
    {
        $asked = is_string($params['protocolVersion'] ?? null) ? $params['protocolVersion'] : '';
        $version = in_array($asked, self::SUPPORTED_PROTOCOLS, true) ? $asked : self::PROTOCOL;

        $company = app()->bound('currentCompany') ? app('currentCompany') : null;
        $name = $audience === McpToken::AUDIENCE_ADMIN
            ? 'ClicFlow Admin MCP'
            : McpOAuthMetadata::displayName($company instanceof Company ? $company : null);

        return [
            'protocolVersion' => $version,
            'capabilities' => [
                'tools' => ['listChanged' => false],
            ],
            'serverInfo' => [
                'name' => $name,
                'title' => $name,
                'version' => '1.0.0',
            ],
            'instructions' => $audience === McpToken::AUDIENCE_ADMIN
                ? 'You are connected to the ClicFlow platform admin. You can list companies, users, subscriptions and platform stats. All actions are audited.'
                : 'You are connected to the ClicFlow workspace "'.$name.'". Only use tools against this workspace. Creating invoices always produces a draft. Write tools require a token with the write scope.',
        ];
    }

    private function tools(string $audience): CompanyMcpTools|AdminMcpTools
    {
        return $audience === McpToken::AUDIENCE_ADMIN ? $this->adminTools : $this->companyTools;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function callTool(Request $request, McpToken $token, string $audience, array $params): array
    {
        $name = isset($params['name']) && is_string($params['name']) ? $params['name'] : '';
        $arguments = is_array($params['arguments'] ?? null) ? $params['arguments'] : [];

        $def = $this->tools($audience)->definition($name);
        if ($def === null) {
            return ['__rpc_error' => true, 'code' => -32602, 'status' => 'error', 'message' => 'Unknown tool: '.$name];
        }

        $needWrite = ($def['scope'] ?? 'read') === 'write';
        if ($needWrite && ! $token->canWrite()) {
            return ['__rpc_error' => true, 'code' => -32003, 'status' => 'denied', 'message' => 'This tool requires the write scope.'];
        }
        if (! $needWrite && ! $token->canRead()) {
            return ['__rpc_error' => true, 'code' => -32003, 'status' => 'denied', 'message' => 'This tool requires the read scope.'];
        }

        $company = $audience === McpToken::AUDIENCE_COMPANY ? $token->company() : null;
        $result = $this->tools($audience)->call($name, $arguments, $token, $company);

        $text = is_string($result) ? $result : json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        return [
            'content' => [
                ['type' => 'text', 'text' => (string) $text],
            ],
            'structuredContent' => is_array($result) ? $result : ['result' => $result],
            'isError' => is_array($result) && ($result['error'] ?? false) === true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function rpcError(mixed $id, int $code, string $message): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => ['code' => $code, 'message' => $message],
        ];
    }

    /** 401 with the RFC 9728 pointer so OAuth clients (ChatGPT, Claude) can discover the authorization server. */
    private function unauthorized(Request $request, string $audience, bool $hadToken, string $message): JsonResponse
    {
        $response = $this->rpcHttpError(401, null, -32001, $message);
        if ($audience === McpToken::AUDIENCE_COMPANY) {
            $header = 'Bearer realm="ClicFlow MCP", resource_metadata="'.McpOAuthMetadata::protectedResourceUrl($request).'", scope="'.implode(' ', McpOAuthMetadata::SCOPES).'"';
            if ($hadToken) {
                $header .= ', error="invalid_token"';
            }
            $response->headers->set('WWW-Authenticate', $header);
        }

        return $response;
    }

    private function rpcHttpError(int $http, mixed $id, int $code, string $message): JsonResponse
    {
        return response()->json($this->rpcError($id, $code, $message), $http);
    }

    private function audit(
        Request $request,
        ?McpToken $token,
        string $audience,
        string $method,
        ?string $tool,
        string $status,
        ?string $error,
    ): void {
        McpAuditLog::record([
            'mcp_token_id' => $token?->id,
            'tokenable_type' => $token?->tokenable_type ?? $audience,
            'tokenable_id' => $token?->tokenable_id,
            'company_id' => $token && $token->tokenable_type === McpToken::AUDIENCE_COMPANY ? $token->tokenable_id : null,
            'method' => $method,
            'tool_name' => $tool,
            'status' => $status,
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
            'error_message' => $error !== null ? mb_substr($error, 0, 500) : null,
        ]);
    }
}
