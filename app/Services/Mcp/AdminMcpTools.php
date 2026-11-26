<?php

namespace App\Services\Mcp;

use App\Models\Company;
use App\Models\McpToken;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class AdminMcpTools
{
    /**
     * @return list<array{name:string,description:string,inputSchema:array<string,mixed>}>
     */
    public function list(): array
    {
        return array_map(fn (array $d) => [
            'name' => $d['name'],
            'description' => $d['description'],
            'inputSchema' => $d['inputSchema'],
        ], $this->catalog());
    }

    /** @return array<string, mixed>|null */
    public function definition(string $name): ?array
    {
        foreach ($this->catalog() as $d) {
            if ($d['name'] === $name) {
                return $d;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function call(string $name, array $arguments, McpToken $token, $company = null): mixed
    {
        $def = $this->definition($name);
        if ($def === null) {
            return ['error' => true, 'message' => 'Unknown tool.'];
        }

        return $this->{$def['handler']}($arguments);
    }

    /** @return list<array<string, mixed>> */
    private function catalog(): array
    {
        $page = [
            'q' => ['type' => 'string'],
            'page' => ['type' => 'integer', 'default' => 1],
            'per_page' => ['type' => 'integer', 'default' => 25],
        ];

        return [
            ['name' => 'list_companies', 'description' => 'List ClicFlow companies (workspaces).', 'scope' => 'read', 'handler' => 'listCompanies', 'inputSchema' => $this->object($page + ['status' => ['type' => 'string']])],
            ['name' => 'list_users', 'description' => 'List users across workspaces, optionally by company.', 'scope' => 'read', 'handler' => 'listUsers', 'inputSchema' => $this->object($page + ['company_id' => ['type' => 'string']])],
            ['name' => 'list_subscriptions', 'description' => 'List workspace subscriptions.', 'scope' => 'read', 'handler' => 'listSubscriptions', 'inputSchema' => $this->object($page + ['status' => ['type' => 'string'], 'company_id' => ['type' => 'string']])],
            ['name' => 'platform_stats', 'description' => 'Platform-wide statistics: companies, users, subscriptions.', 'scope' => 'read', 'handler' => 'platformStats', 'inputSchema' => $this->object([])],
        ];
    }

    /** @param  array<string, mixed>  $properties */
    private function object(array $properties): array
    {
        return ['type' => 'object', 'properties' => $properties];
    }

    /** @return array{page:int,per_page:int} */
    private function page(array $args): array
    {
        return [
            'page' => max(1, (int) ($args['page'] ?? 1)),
            'per_page' => min(100, max(1, (int) ($args['per_page'] ?? 25))),
        ];
    }

    /** @param  callable(\Illuminate\Database\Eloquent\Model): array<string, mixed>  $map */
    private function paginate(Builder $query, array $args, callable $map): array
    {
        $p = $this->page($args);
        $paginator = $query->paginate($p['per_page'], ['*'], 'page', $p['page']);

        return [
            'data' => $paginator->getCollection()->map($map)->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    /** @param  array<string, mixed>  $args */
    private function listCompanies(array $args): array
    {
        $q = Company::query()->withoutGlobalScopes()->with('plan:id,name,slug')->latest();
        if (! empty($args['q'])) {
            $term = '%'.trim((string) $args['q']).'%';
            $q->where(function ($sub) use ($term): void {
                $sub->where('name', 'like', $term)->orWhere('subdomain', 'like', $term)->orWhere('contact_email', 'like', $term);
            });
        }
        if (isset($args['status']) && $args['status'] === 'disabled') {
            $q->where('is_enabled', false);
        } elseif (isset($args['status']) && $args['status'] === 'enabled') {
            $q->where('is_enabled', true);
        }

        return $this->paginate($q, $args, fn (Company $c) => [
            'id' => $c->id,
            'name' => $c->name,
            'subdomain' => $c->subdomain,
            'contact_email' => $c->contact_email,
            'is_enabled' => (bool) $c->is_enabled,
            'plan' => $c->plan?->name,
            'default_locale' => $c->default_locale,
            'default_currency' => $c->default_currency,
            'created_at' => $c->created_at?->toIso8601String(),
        ]);
    }

    /** @param  array<string, mixed>  $args */
    private function listUsers(array $args): array
    {
        $q = User::query()->with(['company:id,name,subdomain', 'roles'])->latest();
        if (! empty($args['company_id'])) {
            $q->where('company_id', $args['company_id']);
        }
        if (! empty($args['q'])) {
            $term = '%'.trim((string) $args['q']).'%';
            $q->where(function ($sub) use ($term): void {
                $sub->where('name', 'like', $term)->orWhere('email', 'like', $term);
            });
        }

        return $this->paginate($q, $args, fn (User $u) => [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'company_id' => $u->company_id,
            'company_name' => $u->company?->name,
            'roles' => $u->roles->pluck('name')->values(),
            'created_at' => $u->created_at?->toIso8601String(),
        ]);
    }

    /** @param  array<string, mixed>  $args */
    private function listSubscriptions(array $args): array
    {
        $q = Subscription::query()->withoutGlobalScopes()->with(['company:id,name,subdomain', 'plan:id,name,slug'])->latest();
        if (! empty($args['company_id'])) {
            $q->where('company_id', $args['company_id']);
        }
        if (! empty($args['status'])) {
            $q->where('status', $args['status']);
        }

        return $this->paginate($q, $args, fn (Subscription $s) => [
            'id' => $s->id,
            'company_id' => $s->company_id,
            'company_name' => $s->company?->name,
            'plan' => $s->plan?->name,
            'status' => $s->status,
            'trial_ends_at' => $s->trial_ends_at?->toIso8601String(),
            'current_period_end' => $s->current_period_end?->toIso8601String(),
        ]);
    }

    /** @param  array<string, mixed>  $args */
    private function platformStats(array $args): array
    {
        $companies = Company::query()->withoutGlobalScopes();

        return [
            'companies' => [
                'total' => (clone $companies)->count(),
                'enabled' => (clone $companies)->where('is_enabled', true)->count(),
            ],
            'users' => [
                'total' => User::query()->count(),
            ],
            'subscriptions' => [
                'total' => Subscription::query()->withoutGlobalScopes()->count(),
                'active' => Subscription::query()->withoutGlobalScopes()->whereIn('status', ['active', 'trialing'])->count(),
            ],
        ];
    }
}
