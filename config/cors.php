<?php

/*
 * Same as the framework defaults, plus the MCP endpoints and OAuth discovery
 * (browser-based MCP clients such as the MCP Inspector need CORS; no credentials).
 */
return [
    'paths' => [
        'api/*',
        'sanctum/csrf-cookie',
        'mcp',
        'mcp/oauth/register',
        'mcp/oauth/token',
        'mcp/oauth/revoke',
        '.well-known/oauth-protected-resource',
        '.well-known/oauth-protected-resource/*',
        '.well-known/oauth-authorization-server',
        '.well-known/oauth-authorization-server/*',
    ],
    'allowed_methods' => ['*'],
    'allowed_origins' => ['*'],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => ['WWW-Authenticate', 'Mcp-Session-Id', 'MCP-Protocol-Version'],
    'max_age' => 0,
    'supports_credentials' => false,
];
