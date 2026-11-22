<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Movie;
use App\Services\TmdbService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class TmdbMovieController extends Controller
{
    public function index(Request $request, TmdbService $tmdb): Response
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'min:2', 'max:80'],
        ]);

        $query = trim((string) ($validated['q'] ?? ''));
        $results = [];
        $tmdbError = null;

        if ($query !== '') {
            try {
                $results = $tmdb->search($query);
            } catch (ConnectionException|RequestException|RuntimeException $exception) {
                report($exception);
                $tmdbError = 'Pencarian TMDB belum tersedia. Periksa token atau coba kembali.';
            }
        }

        return Inertia::render('admin/movies/Import', [
            'query' => $query,
            'results' => $results,
            'importedIds' => Movie::query()
                ->whereIn('tmdb_id', array_column($results, 'id'))
                ->pluck('tmdb_id')
                ->all(),
            'tmdbError' => $tmdbError,
            'success' => $request->session()->get('success'),
        ]);
    }

    public function store(int $tmdbId, TmdbService $tmdb): RedirectResponse
    {
        abort_if($tmdbId < 1, 404);

        if (Movie::query()->where('tmdb_id', $tmdbId)->exists()) {
            return back()->with('success', 'Film sudah ada di katalog CINEM4.');
        }

        try {
            $details = $tmdb->details($tmdbId);

            if ((int) ($details['id'] ?? 0) !== $tmdbId || ($details['adult'] ?? false)) {
                return back()->withErrors(['tmdb' => 'Film tidak dapat diimpor dari TMDB.']);
            }

            $attributes = $tmdb->movieAttributes($details);
            Movie::query()->firstOrCreate(['tmdb_id' => $tmdbId], $attributes);
        } catch (ConnectionException|RequestException|RuntimeException $exception) {
            report($exception);

            return back()->withErrors(['tmdb' => 'Gagal mengambil detail film dari TMDB. Coba kembali.']);
        }

        return back()->with('success', 'Film berhasil ditambahkan ke katalog sebagai Coming Soon.');
    }
}
