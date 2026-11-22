<?php

namespace App\Http\Controllers;

use App\Models\Movie;
use Inertia\Inertia;
use Inertia\Response;

class MovieCatalogController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('movies/Index', [
            'movies' => Movie::query()
                ->where('is_active', true)
                ->orderByDesc('created_at')
                ->simplePaginate(12, [
                    'id_movie', 'title', 'slug', 'genre', 'duration_minute',
                    'status', 'poster', 'synopsis', 'release_date',
                ]),
        ]);
    }
}
