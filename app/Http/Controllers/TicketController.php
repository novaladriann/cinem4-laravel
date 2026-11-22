<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TicketController extends Controller
{
    public function qr(Request $request, Booking $booking): Response
    {
        abort_unless($booking->id_user === (int) $request->user()->getAuthIdentifier(), 403);
        abort_unless($booking->status === 'paid', 404);
        abort_if($booking->needsPaymentReview(), 409, 'Tiket menunggu pemeriksaan pembayaran.');

        $ticket = $booking->ticket()->firstOrFail();

        $writer = new Writer(new ImageRenderer(new RendererStyle(250), new SvgImageBackEnd));
        $svg = $writer->writeString(route('admin.tickets.verify', $ticket->code));

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
