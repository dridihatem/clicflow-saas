<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Services\ClientPortalAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ClientPortalAccountController extends Controller
{
    public function __construct(
        private ClientPortalAccountService $accounts,
    ) {
        $this->middleware(function ($request, $next) {
            abort_if(! $request->user()?->hasAnyRole(['company_admin', 'team_member']), 403);

            return $next($request);
        });
    }

    private function assertWorkspaceClient(Request $request, Client $client): void
    {
        $company = $request->user()->company;
        abort_if(! $company, 403);
        abort_if((string) $client->company_id !== (string) $company->id, 404);
    }

    public function store(Request $request, Client $client): RedirectResponse
    {
        $this->assertWorkspaceClient($request, $client);
        $company = $request->user()->company;

        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['nullable', 'string', 'min:8', 'max:255'],
            'send_credentials' => ['nullable', 'boolean'],
            'send_mode' => ['nullable', 'in:link,password,none'],
        ]);

        $mode = $data['send_mode'] ?? (($data['send_credentials'] ?? false) ? 'password' : 'none');
        $password = filled($data['password'] ?? null)
            ? (string) $data['password']
            : $this->accounts->generatePassword();

        $result = $this->accounts->create(
            $client,
            $company,
            (string) $data['email'],
            $password,
            $mode === 'password',
            $request->user()->locale,
        );

        if ($mode === 'link') {
            $link = $this->accounts->sendAccessLink($client->fresh(), $company);

            return $this->backWithLink($client, $link['mailed'], __('client_portal_created'), $password);
        }

        return $this->backWithPassword($client, $password, $result['mailed'], $result['mail_error'], 'created');
    }

    public function resetPassword(Request $request, Client $client): RedirectResponse
    {
        $this->assertWorkspaceClient($request, $client);
        $company = $request->user()->company;

        $data = $request->validate([
            'password' => ['nullable', 'string', 'min:8', 'max:255'],
            'send_credentials' => ['nullable', 'boolean'],
        ]);

        $password = filled($data['password'] ?? null)
            ? (string) $data['password']
            : $this->accounts->generatePassword();

        $result = $this->accounts->resetPassword(
            $client,
            $company,
            $password,
            (bool) ($data['send_credentials'] ?? false),
        );

        return $this->backWithPassword($client, $password, $result['mailed'], $result['mail_error'], 'reset');
    }

    public function sendAccessLink(Request $request, Client $client): RedirectResponse
    {
        $this->assertWorkspaceClient($request, $client);
        $result = $this->accounts->sendAccessLink($client, $request->user()->company);

        return $this->backWithLink($client, $result['mailed']);
    }

    private function backWithLink(Client $client, bool $mailed, ?string $prefix = null, ?string $password = null): RedirectResponse
    {
        $message = trim(($prefix ? $prefix.' ' : '').($mailed ? __('client_portal_access_link_sent') : __('client_portal_access_link_failed')));
        $redirect = redirect()
            ->route('clients.show', ['client' => $client, 'tab' => 'portal'])
            ->with($mailed ? 'status' : 'error', $message);
        if ($password !== null && ! $mailed) {
            $redirect->with('portal_plain_password', $password);
        }

        return $redirect;
    }

    public function disable(Request $request, Client $client): RedirectResponse
    {
        $this->assertWorkspaceClient($request, $client);
        $this->accounts->disable($client);

        return redirect()
            ->route('clients.show', ['client' => $client, 'tab' => 'portal'])
            ->with('status', __('client_portal_disabled'));
    }

    public function enable(Request $request, Client $client): RedirectResponse
    {
        $this->assertWorkspaceClient($request, $client);
        $this->accounts->enable($client);

        return redirect()
            ->route('clients.show', ['client' => $client, 'tab' => 'portal'])
            ->with('status', __('client_portal_enabled'));
    }

    private function backWithPassword(Client $client, string $password, bool $mailed, ?string $mailError, string $kind): RedirectResponse
    {
        $message = $kind === 'created'
            ? __('client_portal_created')
            : __('client_portal_password_reset');

        if ($mailed) {
            $message .= ' '.__('client_portal_credentials_sent');
        } elseif ($mailError) {
            $message .= ' '.__('client_portal_credentials_send_failed');
        }

        return redirect()
            ->route('clients.show', ['client' => $client, 'tab' => 'portal'])
            ->with('status', $message)
            ->with('portal_plain_password', $password);
    }
}
