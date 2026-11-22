<?php

namespace App\Http\Controllers;

use App\Models\Schedule;
use App\Services\SeatReservationService;
use Inertia\Inertia;
use Inertia\Response;

class ShowtimeController extends Controller
{
    public function index(): Response
    {
        $schedules = Schedule::query()
            ->with(['movie', 'cinema', 'studio'])
            ->where('is_active', true)
            ->where('status', 'open')
            ->whereHas('movie', fn ($query) => $query->where('is_active', true)->where('status', 'now_showing'))
            ->whereHas('cinema', fn ($query) => $query->where('is_active', true))
            ->whereHas('studio', fn ($query) => $query->where('is_active', true))
            ->whereDate('show_date', '>=', now('Asia/Jakarta')->toDateString())
            ->orderBy('show_date')
            ->orderBy('show_time')
            ->limit(100)
            ->get()
            ->filter(fn (Schedule $schedule): bool => app(SeatReservationService::class)->isBookable($schedule))
            ->values();

        return Inertia::render('showtimes/Index', [
            'schedules' => $schedules,
        ]);
    }

    public function show(Schedule $schedule, SeatReservationService $reservations): Response
    {
        $schedule->load(['movie', 'cinema', 'studio']);
        abort_unless($reservations->isBookable($schedule), 404);

        return Inertia::render('showtimes/Show', [
            'schedule' => $schedule,
            'occupiedSeats' => $reservations->occupiedSeats($schedule->id_schedule),
            'maxSeats' => SeatReservationService::MAX_SEATS,
            'holdMinutes' => SeatReservationService::HOLD_MINUTES,
        ]);
    }
}
