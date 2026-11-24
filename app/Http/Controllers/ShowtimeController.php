<?php

namespace App\Http\Controllers;

use App\Models\Schedule;
use App\Services\SeatReservationService;
use App\Services\TmdbService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ShowtimeController extends Controller
{
    public function index(SeatReservationService $reservations, TmdbService $tmdb): Response
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
            ->filter(fn (Schedule $schedule): bool => $reservations->isBookable($schedule))
            ->values();

        $movieShowcases = $schedules
            ->pluck('movie')
            ->filter()
            ->unique('id_movie')
            ->mapWithKeys(fn ($movie): array => [
                (string) $movie->id_movie => $tmdb->showcase($movie->tmdb_id),
            ])
            ->all();

        $occupiedSeatsBySchedule = $schedules
            ->mapWithKeys(fn (Schedule $schedule): array => [
                (string) $schedule->id_schedule => $reservations->occupiedSeats($schedule->id_schedule),
            ])
            ->all();

        return Inertia::render('showtimes/Index', [
            'schedules' => $schedules,
            'movieShowcases' => $movieShowcases,
            'occupiedSeatsBySchedule' => $occupiedSeatsBySchedule,
            'maxSeats' => SeatReservationService::MAX_SEATS,
            'holdMinutes' => SeatReservationService::HOLD_MINUTES,
        ]);
    }

    public function seats(Schedule $schedule, SeatReservationService $reservations): JsonResponse
    {
        $schedule->load(['movie', 'cinema', 'studio']);
        abort_unless($reservations->isBookable($schedule), 404);

        return response()->json([
            'id_schedule' => $schedule->id_schedule,
            'occupiedSeats' => $reservations->occupiedSeats($schedule->id_schedule),
        ]);
    }

    public function show(Request $request, Schedule $schedule, SeatReservationService $reservations, TmdbService $tmdb): Response
    {
        $schedule->load(['movie', 'cinema', 'studio']);
        abort_unless($reservations->isBookable($schedule), 404);

        $siblingSchedules = Schedule::query()
            ->with(['cinema', 'studio'])
            ->where('id_movie', $schedule->id_movie)
            ->where('is_active', true)
            ->where('status', 'open')
            ->whereHas('cinema', fn ($query) => $query->where('is_active', true))
            ->whereHas('studio', fn ($query) => $query->where('is_active', true))
            ->whereDate('show_date', '>=', now('Asia/Jakarta')->toDateString())
            ->orderBy('show_date')
            ->orderBy('show_time')
            ->limit(40)
            ->get()
            ->filter(fn (Schedule $item): bool => $reservations->isBookable($item))
            ->values();

        $movie = $schedule->movie;
        $showcase = $tmdb->showcase($movie?->tmdb_id);

        if ($movie !== null) {
            if ((! is_string($movie->synopsis) || trim($movie->synopsis) === '') && is_string($showcase['synopsis'])) {
                $movie->synopsis = $showcase['synopsis'];
            }
            if ((! is_string($movie->trailer_url) || trim($movie->trailer_url) === '') && is_string($showcase['trailer_url'])) {
                $movie->trailer_url = $showcase['trailer_url'];
            }
        }

        return Inertia::render('showtimes/Show', [
            'schedule' => $schedule,
            'movieShowcase' => $showcase,
            'siblingSchedules' => $siblingSchedules,
            'occupiedSeats' => $reservations->occupiedSeats($schedule->id_schedule),
            'maxSeats' => SeatReservationService::MAX_SEATS,
            'holdMinutes' => SeatReservationService::HOLD_MINUTES,
            'initialViewMode' => $request->query('view') === 'detail' ? 'detail' : 'booking',
        ]);
    }
}
