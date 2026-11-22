<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Services\MidtransSandboxService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class MidtransPaymentController extends Controller
{
    public function start(Request $request, Booking $booking, MidtransSandboxService $payments): Response
    {
        abort_unless($booking->id_user === (int) $request->user()->getAuthIdentifier(), 403);

        try {
            $url = $payments->initiate($booking);
        } catch (ConnectionException|RequestException $exception) {
            report($exception);

            return to_route('bookings.show', $booking)
                ->withErrors(['payment' => 'Midtrans Sandbox belum dapat dihubungi. Silakan periksa status pembayaran.']);
        }

        return Inertia::location($url);
    }

    public function finish(Request $request, Booking $booking, MidtransSandboxService $payments): RedirectResponse
    {
        abort_unless($booking->id_user === (int) $request->user()->getAuthIdentifier(), 403);

        // Ignore untrusted query parameters from the browser; verify against Midtrans directly.
        if ($booking->status === 'pending' && $booking->midtrans_order_id !== null) {
            try {
                $payments->reconcile($booking);
            } catch (ConnectionException|RequestException $exception) {
                report($exception);

                return to_route('bookings.show', $booking)->withErrors([
                    'payment' => 'Status pembayaran belum dapat diperiksa. Gunakan tombol Periksa Status Pembayaran.',
                ]);
            }
        }

        return to_route('bookings.show', $booking);
    }

    public function refresh(Request $request, Booking $booking, MidtransSandboxService $payments): RedirectResponse
    {
        abort_unless($booking->id_user === (int) $request->user()->getAuthIdentifier(), 403);

        try {
            $payments->reconcile($booking);
        } catch (ConnectionException|RequestException $exception) {
            report($exception);

            return back()->withErrors(['payment' => 'Gagal menghubungi Midtrans. Silakan coba kembali.']);
        }

        return to_route('bookings.show', $booking);
    }

    public function webhook(Request $request, MidtransSandboxService $payments): Response
    {
        $payments->handleNotification($request->json()->all());

        return response()->json(['received' => true]);
    }
}
