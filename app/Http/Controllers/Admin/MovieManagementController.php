<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Movie;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MovieManagementController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('admin/movies/Index', [
            'movies' => Movie::query()
                ->orderByDesc('updated_at')
                ->orderByDesc('id_movie')
                ->simplePaginate(12, [
                    'id_movie', 'title', 'genre', 'poster', 'status',
                    'is_active', 'is_featured', 'tmdb_id',
                ]),
            'success' => $request->session()->get('success'),
        ]);
    }

    public function edit(Movie $movie): Response
    {
        return Inertia::render('admin/movies/Edit', [
            'movie' => $movie->only([
                'id_movie', 'title', 'poster', 'tmdb_id', 'genre',
                'status', 'is_active', 'is_featured',
            ]),
        ]);
    }

    public function update(Request $request, Movie $movie): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['now_showing', 'coming_soon'])],
            'is_active' => ['required', 'boolean'],
            'is_featured' => ['required', 'boolean'],
        ]);

        $movie->update($validated);

        return to_route('admin.movies.index')->with('success', 'Pengaturan film berhasil disimpan.');
    }
}
