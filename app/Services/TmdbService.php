<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class TmdbService
{
    /**
     * @return array<int, array{id: int, title: string, release_date: string, overview: string, poster: string|null}>
     */
    public function search(string $query): array
    {
        return Cache::remember('tmdb:search:'.sha1(mb_strtolower(trim($query))), now()->addMinutes(15), function () use ($query): array {
            $data = $this->request('search/movie', [
                'query' => $query,
                'language' => 'id-ID',
                'include_adult' => 'false',
            ]);

            $rawResults = (array) ($data['results'] ?? []);

            /** @var array<int, array<string, mixed>> $results */
            $results = array_filter($rawResults, 'is_array');

            return collect($results)
                ->filter(fn (array $movie): bool => is_int($movie['id'] ?? null) && ($movie['adult'] ?? false) === false)
                ->take(20)
                ->map(fn (array $movie): array => [
                    'id' => $movie['id'],
                    'title' => (string) ($movie['title'] ?? ''),
                    'release_date' => (string) ($movie['release_date'] ?? ''),
                    'overview' => Str::limit((string) ($movie['overview'] ?? ''), 180),
                    'poster' => $this->imageUrl($movie['poster_path'] ?? null, 'w342'),
                ])
                ->values()
                ->all();
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function details(int $tmdbId): array
    {
        return Cache::remember('tmdb:movie:'.$tmdbId, now()->addHours(12), fn (): array => $this->request('movie/'.$tmdbId, [
            'language' => 'id-ID',
            'append_to_response' => 'videos,credits',
        ]));
    }

    /**
     * Fetch rich TMDB showcase metadata (cast, director, country, language, score, trailer, synopsis fallback).
     *
     * @return array{
     *     grade: float|null,
     *     country: string|null,
     *     language: string|null,
     *     director: string|null,
     *     synopsis: string|null,
     *     trailer_url: string|null,
     *     trailer_embed_url: string|null,
     *     cast: array<int, array{name: string, character: string|null, photo: string|null}>
     * }
     */
    public function showcase(?int $tmdbId): array
    {
        $empty = [
            'grade' => null,
            'country' => null,
            'language' => null,
            'director' => null,
            'synopsis' => null,
            'trailer_url' => null,
            'trailer_embed_url' => null,
            'cast' => [],
        ];

        if ($tmdbId === null || $tmdbId < 1) {
            return $empty;
        }

        $token = config('services.tmdb.token');
        if (! is_string($token) || trim($token) === '') {
            return $empty;
        }

        try {
            return Cache::remember('tmdb:showcase:v2:'.$tmdbId, now()->addHours(12), function () use ($tmdbId): array {
                $data = $this->request('movie/'.$tmdbId, [
                    'language' => 'id-ID',
                    'append_to_response' => 'videos,credits',
                ]);

                $overview = trim((string) ($data['overview'] ?? ''));
                $videoKey = $this->extractYoutubeKey((array) data_get($data, 'videos.results', []));

                if ($overview === '' || $videoKey === null) {
                    try {
                        $enData = $this->request('movie/'.$tmdbId, [
                            'language' => 'en-US',
                            'append_to_response' => 'videos',
                        ]);
                        if ($overview === '') {
                            $overview = trim((string) ($enData['overview'] ?? ''));
                        }
                        if ($videoKey === null) {
                            $videoKey = $this->extractYoutubeKey((array) data_get($enData, 'videos.results', []));
                        }
                    } catch (\Throwable) {
                        // Ignore English fallback failure
                    }
                }

                $rawCountries = array_filter((array) ($data['production_countries'] ?? []), 'is_array');
                $countries = collect($rawCountries)
                    ->map(fn (array $c): string => trim((string) ($c['iso_3166_1'] ?? $c['name'] ?? '')))
                    ->filter()
                    ->take(3)
                    ->implode(', ');

                $rawLanguages = array_filter((array) ($data['spoken_languages'] ?? []), 'is_array');
                $language = collect($rawLanguages)
                    ->map(fn (array $l): string => trim((string) ($l['english_name'] ?? $l['name'] ?? '')))
                    ->filter()
                    ->first();

                $rawCrew = array_filter((array) data_get($data, 'credits.crew', []), 'is_array');
                $director = collect($rawCrew)
                    ->filter(fn (array $member): bool => ($member['job'] ?? null) === 'Director')
                    ->map(fn (array $member): string => trim((string) ($member['name'] ?? '')))
                    ->filter()
                    ->take(2)
                    ->implode(', ');

                $rawCast = array_filter((array) data_get($data, 'credits.cast', []), 'is_array');
                $cast = collect($rawCast)
                    ->filter(fn (array $actor): bool => is_string($actor['name'] ?? null) && trim($actor['name']) !== '')
                    ->take(10)
                    ->map(fn (array $actor): array => [
                        'name' => trim((string) $actor['name']),
                        'character' => trim((string) ($actor['character'] ?? '')) ?: null,
                        'photo' => $this->imageUrl($actor['profile_path'] ?? null, 'w342'),
                    ])
                    ->values()
                    ->all();

                $voteAverage = (float) ($data['vote_average'] ?? 0);

                return [
                    'grade' => $voteAverage > 0 ? round($voteAverage, 1) : null,
                    'country' => $countries !== '' ? $countries : null,
                    'language' => is_string($language) && $language !== '' ? $language : null,
                    'director' => $director !== '' ? $director : null,
                    'synopsis' => $overview !== '' ? $overview : null,
                    'trailer_url' => $videoKey ? 'https://www.youtube.com/watch?v='.$videoKey : null,
                    'trailer_embed_url' => $videoKey ? 'https://www.youtube.com/embed/'.$videoKey.'?autoplay=1&rel=0' : null,
                    'cast' => $cast,
                ];
            });
        } catch (\Throwable) {
            return $empty;
        }
    }

    /**
     * @param  array<int, mixed>  $rawVideos
     */
    private function extractYoutubeKey(array $rawVideos): ?string
    {
        $videos = array_filter($rawVideos, 'is_array');

        $preferred = collect($videos)->first(
            fn (array $item): bool => ($item['site'] ?? null) === 'YouTube'
                && in_array($item['type'] ?? null, ['Trailer', 'Teaser'], true)
                && preg_match('/^[A-Za-z0-9_-]{11}$/', (string) ($item['key'] ?? '')) === 1
        );

        if (is_array($preferred)) {
            return (string) $preferred['key'];
        }

        $anyYoutube = collect($videos)->first(
            fn (array $item): bool => ($item['site'] ?? null) === 'YouTube'
                && preg_match('/^[A-Za-z0-9_-]{11}$/', (string) ($item['key'] ?? '')) === 1
        );

        return is_array($anyYoutube) ? (string) $anyYoutube['key'] : null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function movieAttributes(array $data): array
    {
        $id = (int) ($data['id'] ?? 0);
        $title = Str::limit(trim((string) ($data['title'] ?? $data['original_title'] ?? '')), 150, '');

        if ($id < 1 || $title === '') {
            throw new RuntimeException('Metadata film dari TMDB tidak lengkap.');
        }

        $rawGenres = (array) ($data['genres'] ?? []);

        /** @var array<int, array<string, mixed>> $genreData */
        $genreData = array_filter($rawGenres, 'is_array');
        $genres = collect($genreData)
            ->pluck('name')
            ->filter(fn (mixed $name): bool => is_string($name) && $name !== '')
            ->take(4)
            ->implode(', ');

        $rawVideoData = (array) data_get($data, 'videos.results', []);

        /** @var array<int, array<string, mixed>> $videoData */
        $videoData = array_filter($rawVideoData, 'is_array');
        $video = collect($videoData)
            ->first(fn (array $item): bool => ($item['site'] ?? null) === 'YouTube'
                && ($item['type'] ?? null) === 'Trailer'
                && preg_match('/^[A-Za-z0-9_-]{11}$/', (string) ($item['key'] ?? '')) === 1);

        $releaseDate = (string) ($data['release_date'] ?? '');

        return [
            'tmdb_id' => $id,
            'title' => $title,
            'slug' => Str::limit(Str::slug($title) ?: 'film', 150, '').'-'.$id,
            'genre' => Str::limit($genres ?: 'Lainnya', 150, ''),
            'duration_minute' => max(0, (int) ($data['runtime'] ?? 0)),
            'release_date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $releaseDate) ? $releaseDate : null,
            'synopsis' => (string) ($data['overview'] ?? ''),
            'poster' => $this->imageUrl($data['poster_path'] ?? null, 'w500'),
            'backdrop' => $this->imageUrl($data['backdrop_path'] ?? null, 'w1280'),
            'trailer_url' => $video ? 'https://www.youtube.com/watch?v='.$video['key'] : null,
            'status' => 'coming_soon',
            'is_active' => true,
        ];
    }

    public function imageUrl(mixed $path, string $size): ?string
    {
        if (is_string($path) === false || preg_match('/^\/[A-Za-z0-9_-]+\.(?:jpg|jpeg|png|webp)$/i', $path) !== 1) {
            return null;
        }

        return 'https://image.tmdb.org/t/p/'.$size.$path;
    }

    /**
     * @param  array<string, string>  $query
     * @return array<string, mixed>
     */
    private function request(string $endpoint, array $query): array
    {
        $token = config('services.tmdb.token');

        if (is_string($token) === false || trim($token) === '') {
            throw new RuntimeException('TMDB_API_READ_TOKEN belum diatur pada server.');
        }

        $response = Http::withToken($token)
            ->acceptJson()
            ->connectTimeout(3)
            ->timeout(10)
            ->get('https://api.themoviedb.org/3/'.$endpoint, $query);

        $response->throw();

        $data = $response->json();

        if (is_array($data) === false) {
            throw new RuntimeException('TMDB mengembalikan respons yang tidak valid.');
        }

        return $data;
    }
}
