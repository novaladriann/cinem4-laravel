<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TicketVerificationController extends Controller
{
    public function show(string $code): Response
    {
        $ticket = Ticket::query()->with(['booking.schedule.movie', 'booking.schedule.cinema', 'booking.seats'])
            ->where('code', $code)->firstOrFail();

        return Inertia::render('admin/tickets/Verify', [
            'ticket' => [
                'code' => $ticket->code,
                'used_at' => $ticket->used_at,
                'needs_review' => $ticket->booking->needsPaymentReview(),
                'booking' => $ticket->booking->only(['id_booking', 'status', 'seat_count']),
                'movie' => $ticket->booking->schedule->movie->title,
                'cinema' => $ticket->booking->schedule->cinema->name,
                'studio' => $ticket->booking->schedule->studio_name,
                'date' => $ticket->booking->schedule->show_date,
                'time' => $ticket->booking->schedule->show_time,
                'seats' => $ticket->booking->seats->pluck('seat_number')->all(),
            ],
        ]);
    }

    public function redeem(string $code): RedirectResponse
    {
        DB::transaction(function () use ($code): void {
            $ticket = Ticket::query()->where('code', $code)->lockForUpdate()->firstOrFail();
            $booking = $ticket->booking()->lockForUpdate()->firstOrFail();

            if ($booking->status !== 'paid' || $ticket->used_at !== null || $booking->needsPaymentReview()) {
                throw ValidationException::withMessages([
                    'ticket' => 'Tiket tidak valid atau sudah digunakan.',
                ]);
            }

            $ticket->update(['used_at' => now()]);
        });

        return back()->with('success', 'Tiket berhasil diverifikasi dan ditandai digunakan.');
    }
}
