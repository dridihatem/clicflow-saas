<?php

namespace App\Http\Controllers;

use App\Enums\ClientBookingStatus;
use App\Models\Client;
use App\Models\ClientBooking;
use App\Services\ClientBookingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClientBookingController extends Controller
{
    public function __construct(private ClientBookingService $bookings)
    {
        $this->middleware(function ($request, $next) {
            abort_if(! $request->user()?->hasAnyRole(['company_admin', 'team_member']), 403);

            return $next($request);
        });
    }

    private function assertClient(Request $request, Client $client): void
    {
        abort_if(! $request->user()->company, 403);
        abort_if((string) $client->company_id !== (string) $request->user()->company->id, 404);
    }

    private function assertBooking(Client $client, ClientBooking $booking): void
    {
        abort_if((string) $booking->client_id !== (string) $client->id, 404);
        abort_if((string) $booking->company_id !== (string) $client->company_id, 404);
    }

    private function back(Client $client, ?string $status = null): RedirectResponse
    {
        $r = redirect()->route('clients.show', ['client' => $client, 'tab' => 'bookings']);

        return $status ? $r->with('status', $status) : $r;
    }

    public function store(Request $request, Client $client): RedirectResponse
    {
        $this->assertClient($request, $client);
        $this->bookings->create($client, $request->validate($this->rules()), $request->user());

        return $this->back($client, __('client_booking_created'));
    }

    public function update(Request $request, Client $client, ClientBooking $booking): RedirectResponse
    {
        $this->assertClient($request, $client);
        $this->assertBooking($client, $booking);
        $this->bookings->update($booking, $request->validate($this->rules(false)), $request->user());

        return $this->back($client, __('client_booking_updated'));
    }

    public function destroy(Request $request, Client $client, ClientBooking $booking): RedirectResponse
    {
        $this->assertClient($request, $client);
        $this->assertBooking($client, $booking);
        $this->bookings->delete($booking);

        return $this->back($client, __('client_booking_deleted'));
    }

    public function import(Request $request, Client $client): RedirectResponse
    {
        $this->assertClient($request, $client);
        $request->validate(['csv' => ['required', 'file', 'mimes:csv,txt,text/csv,text/plain', 'max:4096']]);
        $result = $this->bookings->importCsv($client, $request->file('csv'), $request->user());
        $message = __('client_booking_import_done', ['count' => $result['created']]);
        if ($result['errors'] !== []) {
            $message .= ' '.__('client_booking_import_with_errors', ['count' => count($result['errors'])]);
        }

        return $this->back($client, $message)->with('client_booking_import_errors', $result['errors']);
    }

    /** @return array<string, mixed> */
    private function rules(bool $creating = true): array
    {
        return [
            'customer_name' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:60'],
            'email' => ['nullable', 'email', 'max:255'],
            'service' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', Rule::enum(ClientBookingStatus::class)],
            'notes' => ['nullable', 'string', 'max:5000'],
            'starts_at' => ['nullable', 'date'],
        ];
    }
}
