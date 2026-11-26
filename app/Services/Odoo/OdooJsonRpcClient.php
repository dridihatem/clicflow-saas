<?php

namespace App\Services\Odoo;

use App\Models\Company;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class OdooJsonRpcClient
{
    public function __construct(
        private OdooConfigService $config,
    ) {}

    /**
     * @return array{uid: int, url: string, database: string, username: string, api_key: string}
     */
    public function authenticate(Company $company, bool $force = false): array
    {
        $c = $this->config->getResolved($company);
        if (! $this->config->isConfigured($company)) {
            throw new RuntimeException(__('odoo_not_configured'));
        }

        $url = (string) $c['url'];
        $database = (string) $c['database'];
        $username = (string) $c['username'];
        $apiKey = (string) $c['api_key'];

        if (! $force && is_int($c['uid']) && $c['uid'] > 0) {
            return [
                'uid' => $c['uid'],
                'url' => $url,
                'database' => $database,
                'username' => $username,
                'api_key' => $apiKey,
            ];
        }

        $uid = $this->call($url, 'common', 'authenticate', [$database, $username, $apiKey, []]);
        if (! is_int($uid) || $uid <= 0) {
            throw new RuntimeException(__('odoo_auth_failed'));
        }

        $this->config->rememberUid($company, $uid);

        return [
            'uid' => $uid,
            'url' => $url,
            'database' => $database,
            'username' => $username,
            'api_key' => $apiKey,
        ];
    }

    /**
     * @param  list<mixed>  $args
     * @param  array<string, mixed>  $kwargs
     */
    public function executeKw(Company $company, string $model, string $method, array $args = [], array $kwargs = []): mixed
    {
        $auth = $this->authenticate($company);

        try {
            return $this->call($auth['url'], 'object', 'execute_kw', [
                $auth['database'],
                $auth['uid'],
                $auth['api_key'],
                $model,
                $method,
                $args,
                $kwargs,
            ]);
        } catch (RuntimeException $e) {
            // Cached UID may be stale after password rotation.
            if (str_contains(strtolower($e->getMessage()), 'access denied')
                || str_contains(strtolower($e->getMessage()), 'session expired')) {
                $auth = $this->authenticate($company, force: true);

                return $this->call($auth['url'], 'object', 'execute_kw', [
                    $auth['database'],
                    $auth['uid'],
                    $auth['api_key'],
                    $model,
                    $method,
                    $args,
                    $kwargs,
                ]);
            }

            throw $e;
        }
    }

    public function testConnection(Company $company): string
    {
        $auth = $this->authenticate($company, force: true);
        $version = $this->call($auth['url'], 'common', 'version', []);
        $server = is_array($version) ? (string) ($version['server_version'] ?? 'Odoo') : 'Odoo';

        return $server;
    }

    /**
     * @param  list<mixed>  $args
     */
    private function call(string $baseUrl, string $service, string $method, array $args): mixed
    {
        $endpoint = rtrim($baseUrl, '/').'/jsonrpc';
        $payload = [
            'jsonrpc' => '2.0',
            'method' => 'call',
            'params' => [
                'service' => $service,
                'method' => $method,
                'args' => $args,
            ],
            'id' => random_int(1, 1_000_000),
        ];

        try {
            $response = Http::timeout(45)
                ->acceptJson()
                ->asJson()
                ->post($endpoint, $payload);
        } catch (ConnectionException $e) {
            throw new RuntimeException(__('odoo_connection_failed', ['message' => $e->getMessage()]), 0, $e);
        } catch (Throwable $e) {
            throw new RuntimeException(__('odoo_connection_failed', ['message' => $e->getMessage()]), 0, $e);
        }

        if (! $response->successful()) {
            throw new RuntimeException(__('odoo_http_error', ['status' => $response->status()]));
        }

        $json = $response->json();
        if (! is_array($json)) {
            throw new RuntimeException(__('odoo_invalid_response'));
        }

        if (isset($json['error'])) {
            $error = $json['error'];
            $message = 'Odoo error';
            if (is_array($error)) {
                $data = $error['data'] ?? null;
                if (is_array($data) && isset($data['message']) && is_string($data['message'])) {
                    $message = $data['message'];
                } elseif (isset($error['message']) && is_string($error['message'])) {
                    $message = $error['message'];
                }
            }

            throw new RuntimeException($message);
        }

        return $json['result'] ?? null;
    }
}
