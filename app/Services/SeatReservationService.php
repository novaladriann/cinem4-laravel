<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Schedule;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SeatReservationService
{
    public const HOLD_MINUTES = 10;

    public const MAX_SEATS = 6;

    public function isBookable(Schedule $schedule): bool
    {
        if (! $schedule->is_active || $schedule->status !== 'open'
            || $schedule->id_studio === null
            || ! $schedule->movie->is_active || $schedule->movie->status !== 'now_showing'
            || ! $schedule->cinema->is_active || ! $schedule->studio->is_active) {
            return false;
        }

        $start = CarbonImmutable::parse(
            substr((string) $schedule->getRawOriginal('show_date'), 0, 10).' '.
            substr((string) $schedule->getRawOriginal('show_time'), 0, 8),
            'Asia/Jakarta',
        );

        return $start->greaterThan(now('Asia/Jakarta')->addMinutes($schedule->booking_close_minutes));
    }

    /**
     * @param  array<int, int>  $seats
     */
    public function hold(Schedule $schedule, int $userId, array $seats): Booking
    {
        try {
            return DB::transaction(function () use ($schedule, $userId, $seats): Booking {
                $schedule = Schedule::query()
                    ->with(['movie', 'cinema', 'studio'])
                    ->whereKey($schedule->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! $this->isBookable($schedule)) {
                    throw ValidationException::withMessages([
                        'seats' => 'Jadwal sudah tidak tersedia untuk pemesanan.',
                    ]);
                }

                if (count($seats) !== count(array_unique($seats))
                    || min($seats) < 1 || max($seats) > $schedule->seat_capacity) {
                    throw ValidationException::withMessages([
                        'seats' => 'Nomor kursi tidak valid untuk studio ini.',
                    ]);
                }

                $this->releaseExpired($schedule->id_schedule);

                $occupied = DB::table('seat_locks')
                    ->where('id_schedule', $schedule->id_schedule)
                    ->whereIn('seat_number', $seats)
                    ->exists();

                if ($occupied) {
                    throw ValidationException::withMessages([
                        'seats' => 'Salah satu kursi sudah dipesan. Silakan pilih kursi lain.',
                    ]);
                }

                // Currency is stored in decimal columns; calculate totals as integer cents.
                $priceCents = (int) round(((float) $schedule->price) * 100);
                $booking = Booking::query()->create([
                    'id_user' => $userId,
                    'id_schedule' => $schedule->id_schedule,
                    'seat_count' => count($seats),
                    'unit_price' => number_format($priceCents / 100, 2, '.', ''),
                    'total_price' => number_format($priceCents * count($seats) / 100, 2, '.', ''),
                    'status' => 'pending',
                    'expires_at' => now()->addMinutes(self::HOLD_MINUTES),
                ]);

                DB::table('booking_seats')->insert(array_map(
                    fn (int $seat): array => [
                        'id_booking' => $booking->id_booking,
                        'seat_number' => $seat,
                    ],
                    $seats,
                ));

                // Composite primary key prevents simultaneous requests from claiming the same seat.
                DB::table('seat_locks')->insert(array_map(
                    fn (int $seat): array => [
                        'id_booking' => $booking->id_booking,
                        'id_schedule' => $schedule->id_schedule,
                        'seat_number' => $seat,
                    ],
                    $seats,
                ));

                return $booking;
            }, attempts: 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'seats' => 'Kursi baru saja dipesan pelanggan lain. Silakan muat ulang pilihan.',
            ]);
        }
    }

    /**
     * @return array<int, int>
     */
    public function occupiedSeats(int $scheduleId): array
    {
        return DB::table('seat_locks')
            ->join('bookings', 'bookings.id_booking', '=', 'seat_locks.id_booking')
            ->where('seat_locks.id_schedule', $scheduleId)
            ->where(function ($query): void {
                $query->where('bookings.status', 'paid')
                    ->orWhere(function ($pending): void {
                        $pending->where('bookings.status', 'pending')
                            ->where('bookings.expires_at', '>', now());
                    });
            })
            ->pluck('seat_locks.seat_number')
            ->map(fn (mixed $seat): int => (int) $seat)
            ->all();
    }

    public function releaseExpired(int $scheduleId): void
    {
        $expired = Booking::query()
            ->where('id_schedule', $scheduleId)
            ->where('status', 'pending')
            ->where('expires_at', '<=', now())
            ->lockForUpdate()
            ->get();

        foreach ($expired as $booking) {
            DB::table('seat_locks')->where('id_booking', $booking->id_booking)->delete();
            $booking->update(['status' => 'expired']);
        }
    }

    public function cancel(Booking $booking): void
    {
        DB::transaction(function () use ($booking): void {
            $booking = Booking::query()
                ->whereKey($booking->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($booking->status !== 'pending') {
                throw ValidationException::withMessages([
                    'booking' => 'Reservasi ini tidak dapat dibatalkan.',
                ]);
            }

            DB::table('seat_locks')->where('id_booking', $booking->id_booking)->delete();
            $booking->update([
                'status' => $booking->expires_at->isPast() ? 'expired' : 'cancelled',
            ]);
        });
    }
}
