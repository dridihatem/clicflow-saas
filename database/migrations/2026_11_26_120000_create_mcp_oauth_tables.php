<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OAuth 2.1 layer for the workspace MCP server (ChatGPT / Claude connectors).
 * Additive only: two new tables + nullable columns on mcp_tokens.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mcp_oauth_clients')) {
            Schema::create('mcp_oauth_clients', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->string('client_id', 80)->unique();
                $table->string('client_secret_hash', 64)->nullable();
                $table->string('name', 120);
                $table->json('redirect_uris');
                $table->string('token_endpoint_auth_method', 32)->default('none');
                $table->string('client_uri', 255)->nullable();
                $table->string('created_ip', 64)->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('mcp_oauth_codes')) {
            Schema::create('mcp_oauth_codes', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->string('code_hash', 64)->unique();
                $table->string('client_id', 80)->index();
                $table->string('company_id');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('redirect_uri', 500);
                $table->string('code_challenge', 128);
                $table->json('scopes');
                $table->string('resource', 255)->nullable();
                $table->string('issuer', 255);
                $table->ulid('mcp_token_id')->nullable();
                $table->timestamp('expires_at');
                $table->timestamp('used_at')->nullable();
                $table->timestamps();
            });
        }

        Schema::table('mcp_tokens', function (Blueprint $table) {
            if (! Schema::hasColumn('mcp_tokens', 'oauth_client_id')) {
                $table->string('oauth_client_id', 80)->nullable()->index();
            }
            if (! Schema::hasColumn('mcp_tokens', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable();
            }
            if (! Schema::hasColumn('mcp_tokens', 'expires_at')) {
                $table->timestamp('expires_at')->nullable();
            }
            if (! Schema::hasColumn('mcp_tokens', 'refresh_token_hash')) {
                $table->string('refresh_token_hash', 64)->nullable()->unique();
            }
            if (! Schema::hasColumn('mcp_tokens', 'refresh_prev_hash')) {
                $table->string('refresh_prev_hash', 64)->nullable()->index();
            }
            if (! Schema::hasColumn('mcp_tokens', 'refresh_rotated_at')) {
                $table->timestamp('refresh_rotated_at')->nullable();
            }
            if (! Schema::hasColumn('mcp_tokens', 'refresh_expires_at')) {
                $table->timestamp('refresh_expires_at')->nullable();
            }
            if (! Schema::hasColumn('mcp_tokens', 'oauth_resource')) {
                $table->string('oauth_resource', 255)->nullable();
            }
        });
    }

    public function down(): void
    {
        // Additive migration: intentionally left non-destructive.
    }
};
