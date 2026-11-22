<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Schedule;
use App\Services\SeatReservationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BookingController extends Controller
{
    public function store(Request $request, Schedule $schedule, SeatReservationService $reservations): RedirectResponse
    {
        $data = $request->validate([
            'seats' => ['required', 'array', 'min:1', 'max:'.SeatReservationService::MAX_SEATS],
            'seats.*' => ['required', 'integer', 'min:1'],
        ]);

        $booking = $reservations->hold($schedule, (int) $request->user()->getAuthIdentifier(), $data['seats']);

        return to_route('bookings.show', $booking);
    }

    public function show(Request $request, Booking $booking): Response
    {
        abort_unless($booking->id_user === (int) $request->user()->getAuthIdentifier(), 403);

        $booking->load(['seats', 'schedule.movie', 'schedule.cinema', 'schedule.studio']);

        // Expiration is derived at read time even if no cleanup worker has run.
        $status = $booking->status === 'pending' && $booking->expires_at->isPast()
            ? 'expired' : $booking->status;

        return Inertia::render('bookings/Show', [
            'booking' => $booking,
            'displayStatus' => $status,
        ]);
    }

    public function cancel(Request $request, Booking $booking, SeatReservationService $reservations): RedirectResponse
    {
        abort_unless($booking->id_user === (int) $request->user()->getAuthIdentifier(), 403);
        $reservations->cancel($booking);

        return to_route('bookings.show', $booking);
    }

    public function index(Request $request): Response
    {
        return Inertia::render('bookings/Index', [
            'bookings' => Booking::query()
                ->with(['seats', 'schedule.movie', 'schedule.cinema'])
                ->where('id_user', (int) $request->user()->getAuthIdentifier())
                ->orderByDesc('created_at')
                ->simplePaginate(10),
        ]);
    }
}
