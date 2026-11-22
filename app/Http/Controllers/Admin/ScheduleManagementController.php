<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cinema;
use App\Models\Movie;
use App\Models\Schedule;
use App\Models\Studio;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ScheduleManagementController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('admin/schedules/Index', [
            'schedules' => Schedule::query()
                ->with(['movie', 'cinema', 'studio'])
                ->orderByDesc('show_date')
                ->orderBy('show_time')
                ->simplePaginate(20),
            'movies' => Movie::query()
                ->where('is_active', true)
                ->where('status', 'now_showing')
                ->where('duration_minute', '>', 0)
                ->orderBy('title')
                ->get(['id_movie', 'title', 'duration_minute']),
            'cinemas' => Cinema::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id_cinema', 'name', 'city']),
            'studios' => Studio::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id_studio', 'id_cinema', 'name', 'seat_capacity', 'turnaround_minutes']),
            'success' => $request->session()->get('success'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'id_movie' => ['required', 'integer', Rule::exists('movies', 'id_movie')],
            'id_cinema' => ['required', 'integer', Rule::exists('cinemas', 'id_cinema')],
            'id_studio' => ['required', 'integer', Rule::exists('studios', 'id_studio')],
            'show_date' => ['required', 'date_format:Y-m-d'],
            'show_time' => ['required', 'date_format:H:i'],
            'price' => ['required', 'numeric', 'between:1000,1000000'],
        ]);

        $start = CarbonImmutable::parse($data['show_date'].' '.$data['show_time'], 'Asia/Jakarta');

        if ($start->lessThanOrEqualTo(now('Asia/Jakarta'))) {
            throw ValidationException::withMessages(['show_date' => 'Jadwal harus berada di masa mendatang (WIB).']);
        }

        DB::transaction(function () use ($data, $start): void {
            // Serialize schedule creation per cinema so two simultaneous admin requests cannot both pass the overlap check.
            $cinema = Cinema::query()->whereKey($data['id_cinema'])->lockForUpdate()->firstOrFail();
            $movie = Movie::query()->whereKey($data['id_movie'])->firstOrFail();
            $studio = Studio::query()->whereKey($data['id_studio'])->firstOrFail();

            if (! $cinema->is_active || ! $studio->is_active || $studio->id_cinema !== $cinema->id_cinema) {
                throw ValidationException::withMessages(['id_studio' => 'Studio harus aktif dan berada di bioskop yang dipilih.']);
            }

            if (! $movie->is_active || $movie->status !== 'now_showing' || $movie->duration_minute < 1) {
                throw ValidationException::withMessages(['id_movie' => 'Film harus aktif, berstatus Now Showing, dan memiliki durasi.']);
            }

            $end = $start->addMinutes($movie->duration_minute + $studio->turnaround_minutes);

            $others = Schedule::query()
                ->with(['movie', 'studio'])
                ->where('id_cinema', $cinema->id_cinema)
                ->where('is_active', true)
                ->where('status', '!=', 'cancelled')
                ->whereBetween('show_date', [$start->subDay()->toDateString(), $end->toDateString()])
                ->where(function ($query) use ($studio): void {
                    $query->where('id_studio', $studio->id_studio)
                        ->orWhere(function ($legacy) use ($studio): void {
                            $legacy->whereNull('id_studio')->where('studio_name', $studio->name);
                        });
                })
                ->get();

            foreach ($others as $other) {
                $otherStart = CarbonImmutable::parse((string) $other->getRawOriginal('show_date').' '.(string) $other->getRawOriginal('show_time'), 'Asia/Jakarta');
                $duration = (int) ($other->movie->duration_minute ?? 0);
                $turnaround = (int) ($other->studio->turnaround_minutes ?? $studio->turnaround_minutes);
                $otherEnd = $otherStart->addMinutes($duration + $turnaround);

                if ($start->lessThan($otherEnd) && $end->greaterThan($otherStart)) {
                    throw ValidationException::withMessages([
                        'show_time' => 'Jadwal bertabrakan dengan tayangan lain atau jeda persiapan studio.',
                    ]);
                }
            }

            Schedule::query()->create([
                'id_movie' => $movie->id_movie,
                'id_cinema' => $cinema->id_cinema,
                'id_studio' => $studio->id_studio,
                'studio_name' => $studio->name,
                'seat_capacity' => $studio->seat_capacity,
                'booking_close_minutes' => 30,
                'show_date' => $data['show_date'],
                'show_time' => $data['show_time'],
                'price' => $data['price'],
                'status' => 'open',
                'is_active' => true,
            ]);
        });

        return to_route('admin.schedules.index')->with('success', 'Jadwal tayang berhasil ditambahkan.');
    }

    public function cancel(Schedule $schedule): RedirectResponse
    {
        // Once booking is enabled, cancellation must first resolve paid tickets and refunds.
        if ($schedule->status !== 'cancelled') {
            $schedule->update(['status' => 'cancelled', 'is_active' => false]);
        }

        return back()->with('success', 'Jadwal dibatalkan tanpa menghapus riwayat.');
    }
}
