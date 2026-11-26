<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_tokens', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('tokenable_type', 32); // company | admin
            $table->string('tokenable_id');
            $table->string('name', 120);
            $table->string('token_prefix', 12);
            $table->string('token_hash', 64)->unique();
            $table->json('scopes'); // ["read"], ["write"], or both
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['tokenable_type', 'tokenable_id']);
            $table->index('revoked_at');
        });

        Schema::create('mcp_audit_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('mcp_token_id')->nullable();
            $table->string('tokenable_type', 32)->nullable();
            $table->string('tokenable_id')->nullable();
            $table->string('company_id')->nullable()->index();
            $table->string('method', 64);
            $table->string('tool_name', 120)->nullable();
            $table->string('status', 24); // ok, error, denied, unauthorized
            $table->string('ip', 64)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->string('error_message', 500)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['mcp_token_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_audit_logs');
        Schema::dropIfExists('mcp_tokens');
    }
};
