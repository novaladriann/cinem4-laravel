<?php

namespace App\Services;

use App\Models\Booking;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MidtransSandboxService
{
    private const SNAP_ENDPOINT = 'https://app.sandbox.midtrans.com/snap/v1/transactions';

    private const STATUS_ENDPOINT = 'https://api.sandbox.midtrans.com/v2/';

    private const PAYMENT_WINDOW_MINUTES = 15;

    public function initiate(Booking $booking): string
    {
        $this->serverKey();

        $booking = DB::transaction(function () use ($booking): Booking {
            // Schedule lock serializes booking creation/cancellation with payment initiation.
            $schedule = $booking->schedule()->lockForUpdate()->firstOrFail();
            $current = Booking::query()->whereKey($booking->getKey())->lockForUpdate()->firstOrFail();

            if ($current->status !== 'pending' || ! $schedule->is_active || $schedule->status !== 'open') {
                throw ValidationException::withMessages(['payment' => 'Reservasi tidak dapat dibayar.']);
            }

            if ($current->midtrans_order_id === null) {
                if (CarbonImmutable::parse((string) $current->expires_at)->isPast()) {
                    throw ValidationException::withMessages(['payment' => 'Reservasi kedaluwarsa, silakan pesan lagi.']);
                }

                // Provider's payment window is 15 minutes; add a buffer for late notifications.
                $current->update([
                    'midtrans_order_id' => 'C4SBX-'.$current->id_booking.'-'.Str::upper(Str::random(8)),
                    'midtrans_started_at' => now(),
                    'expires_at' => now()->addMinutes(20),
                ]);
            }

            return $current;
        });

        if ($booking->midtrans_redirect_url !== null) {
            return $this->validatedRedirect($booking->midtrans_redirect_url);
        }

        // Reuse the persisted order id on retries. Never create a second charge id.
        $response = Http::withBasicAuth($this->serverKey(), '')
            ->acceptJson()->asJson()->connectTimeout(3)->timeout(12)
            ->post(self::SNAP_ENDPOINT, [
                'transaction_details' => [
                    'order_id' => $booking->midtrans_order_id,
                    'gross_amount' => (int) $booking->total_price,
                ],
                'expiry' => [
                    'start_time' => CarbonImmutable::parse((string) $booking->midtrans_started_at)
                        ->timezone('Asia/Jakarta')->format('Y-m-d H:i:s O'),
                    'unit' => 'minutes',
                    'duration' => self::PAYMENT_WINDOW_MINUTES,
                ],
            ]);

        $response->throw();

        $url = $this->validatedRedirect((string) $response->json('redirect_url'));

        DB::transaction(function () use ($booking, $url): void {
            $current = Booking::query()->whereKey($booking->getKey())->lockForUpdate()->firstOrFail();

            if ($current->status !== 'pending') {
                throw ValidationException::withMessages(['payment' => 'Status reservasi sudah berubah.']);
            }

            $current->update(['midtrans_redirect_url' => $url]);
        });

        return $url;
    }

    /** @param array<string, mixed> $notification */
    public function handleNotification(array $notification): void
    {
        $orderId = (string) ($notification['order_id'] ?? '');
        $code = (string) ($notification['status_code'] ?? '');
        $amount = (string) ($notification['gross_amount'] ?? '');
        $signature = (string) ($notification['signature_key'] ?? '');

        if ($orderId === '' || $code === '' || $amount === '' || $signature === '') {
            abort(400, 'Notifikasi pembayaran tidak lengkap.');
        }

        if (! hash_equals(hash('sha512', $orderId.$code.$amount.$this->serverKey()), $signature)) {
            abort(403, 'Tanda tangan pembayaran tidak valid.');
        }

        // Verify the *latest* status directly with Midtrans; signed notifications can arrive out of order.
        $booking = Booking::query()->where('midtrans_order_id', $orderId)->firstOrFail();
        $this->reconcile($booking);
    }

    public function reconcile(Booking $booking): void
    {
        if ($booking->midtrans_order_id === null) {
            throw ValidationException::withMessages(['payment' => 'Sesi pembayaran belum dibuat.']);
        }

        $response = Http::withBasicAuth($this->serverKey(), '')
            ->acceptJson()->connectTimeout(3)->timeout(10)
            ->get(self::STATUS_ENDPOINT.rawurlencode($booking->midtrans_order_id).'/status');

        $response->throw();
        $payload = $response->json();

        if (! is_array($payload)) {
            throw ValidationException::withMessages(['payment' => 'Respons status Midtrans tidak valid.']);
        }

        $this->applyStatus($payload, $booking->midtrans_order_id);
    }

    /** @param array<string, mixed> $payload */
    private function applyStatus(array $payload, string $expectedOrderId): void
    {
        $orderId = (string) ($payload['order_id'] ?? '');
        $amount = (string) ($payload['gross_amount'] ?? '');
        $status = strtolower((string) ($payload['transaction_status'] ?? ''));

        if (! hash_equals($expectedOrderId, $orderId)
            || ! preg_match('/^[0-9]+(?:\.[0-9]{1,2})?$/', $amount)
            || $status === '') {
            abort(422, 'Status transaksi Midtrans tidak sesuai.');
        }

        DB::transaction(function () use ($orderId, $amount, $status, $payload): void {
            $reference = Booking::query()->where('midtrans_order_id', $orderId)->firstOrFail();
            $schedule = $reference->schedule()->lockForUpdate()->firstOrFail();
            $booking = Booking::query()->whereKey($reference->getKey())->lockForUpdate()->firstOrFail();

            if (number_format((float) $amount, 2, '.', '') !== number_format((float) $booking->total_price, 2, '.', '')) {
                abort(422, 'Nominal pembayaran tidak cocok.');
            }

            $success = in_array($status, ['settlement', 'capture'], true)
                && (string) ($payload['status_code'] ?? '') === '200'
                && ($status !== 'capture' || ($payload['fraud_status'] ?? 'accept') === 'accept');

            if ($success) {
                if ($booking->status === 'paid') {
                    return;
                }

                $hasAllSeats = DB::table('seat_locks')->where('id_booking', $booking->id_booking)->count() === $booking->seat_count;

                if ($booking->status !== 'pending' || ! $hasAllSeats
                    || ! $schedule->is_active || $schedule->status !== 'open') {
                    $this->flagForReview($booking, $status, 'Pembayaran berhasil tetapi booking tidak dapat diterbitkan.');

                    return;
                }

                $booking->update([
                    'status' => 'paid',
                    'paid_at' => now(),
                    'expires_at' => null,
                ]);

                return;
            }

            if (in_array($status, ['expire', 'cancel', 'deny', 'failure'], true)) {
                if ($booking->status === 'paid') {
                    $this->flagForReview($booking, $status, 'Status pembayaran berubah setelah berhasil.');

                    return;
                }

                if ($booking->status === 'pending') {
                    DB::table('seat_locks')->where('id_booking', $booking->id_booking)->delete();
                    $booking->update(['status' => $status === 'expire' ? 'expired' : 'cancelled']);
                }

                return;
            }

            if (in_array($status, ['refund', 'partial_refund', 'chargeback', 'partial_chargeback'], true)) {
                $this->flagForReview($booking, $status, 'Refund/chargeback membutuhkan pemeriksaan admin.');
            }
        }, attempts: 3);
    }

    private function flagForReview(Booking $booking, string $status, string $reason): void
    {
        DB::table('payment_reviews')->updateOrInsert(
            ['id_booking' => $booking->id_booking],
            ['midtrans_status' => $status, 'reason' => $reason, 'created_at' => now(), 'updated_at' => now()],
        );
    }

    private function serverKey(): string
    {
        $key = config('services.midtrans.sandbox_server_key');

        if (! is_string($key) || trim($key) === '') {
            throw ValidationException::withMessages(['payment' => 'Midtrans Sandbox belum dikonfigurasi.']);
        }

        return $key;
    }

    private function validatedRedirect(string $url): string
    {
        $parsed = parse_url($url);

        if (! is_array($parsed) || ($parsed['scheme'] ?? null) !== 'https'
            || ($parsed['host'] ?? null) !== 'app.sandbox.midtrans.com'
            || ! str_starts_with((string) ($parsed['path'] ?? ''), '/snap/')
            || isset($parsed['port']) || isset($parsed['user'])) {
            throw ValidationException::withMessages(['payment' => 'Tautan pembayaran Midtrans tidak valid.']);
        }

        return $url;
    }
}
