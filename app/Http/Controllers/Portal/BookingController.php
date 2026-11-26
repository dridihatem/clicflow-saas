<?php

namespace App\Http\Controllers\Portal;

use App\Enums\ClientBookingStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Concerns\ResolvesPortalClient;
use App\Models\ClientBooking;
use App\Services\ClientBookingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BookingController extends Controller
{
    use ResolvesPortalClient;

    public function __construct(private ClientBookingService $bookings) {}

    public function index(Request $request): View
    {
        $client = $this->portalClient($request);
        $query = ClientBooking::query()
            ->where('client_id', $client->id)
            ->with(['comments' => fn ($q) => $q->limit(8)]);

        $q = $request->string('q')->trim()->toString();
        if ($q !== '') {
            $query->where(function ($b) use ($q): void {
                $b->where('customer_name', 'like', '%'.$q.'%')
                    ->orWhere('phone', 'like', '%'.$q.'%')
                    ->orWhere('email', 'like', '%'.$q.'%')
                    ->orWhere('service', 'like', '%'.$q.'%')
                    ->orWhere('notes', 'like', '%'.$q.'%');
            });
        }
        $status = $request->string('status')->toString();
        if ($status !== '' && ClientBookingStatus::tryFrom($status)) {
            $query->where('status', $status);
        }

        $bookings = $query->orderByDesc('starts_at')->orderByDesc('created_at')->paginate(50)->withQueryString();

        return view('portal.bookings.index', [
            'client' => $client,
            'bookings' => $bookings,
            'statuses' => ClientBookingStatus::cases(),
            'filters' => ['q' => $q, 'status' => $status],
        ]);
    }

    public function updateStatus(Request $request, ClientBooking $booking): RedirectResponse
    {
        $client = $this->portalClient($request);
        $this->authorizeBooking($client, $booking);
        $data = $request->validate([
            'status' => ['required', 'string', Rule::enum(ClientBookingStatus::class)],
        ]);
        $this->bookings->changeStatus($booking, ClientBookingStatus::from($data['status']), $request->user());

        return back()->with('status', __('client_booking_status_saved'));
    }

    public function comment(Request $request, ClientBooking $booking): RedirectResponse
    {
        $client = $this->portalClient($request);
        $this->authorizeBooking($client, $booking);
        $data = $request->validate(['notes' => ['required', 'string', 'max:5000']]);
        $this->bookings->addComment($booking, $data['notes'], $request->user());

        return back()->with('status', __('client_booking_comment_saved'));
    }

    private function authorizeBooking($client, ClientBooking $booking): void
    {
        abort_if((string) $booking->company_id !== (string) $client->company_id, 403);
        abort_if((string) $booking->client_id !== (string) $client->id, 403);
    }
}
