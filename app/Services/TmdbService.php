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

            /** @var array<int, array<string, mixed>> $results */
            $results = is_array($data['results'] ?? null) ? $data['results'] : [];

            return collect($results)
                ->filter(fn (mixed $movie): bool => is_array($movie) && is_int($movie['id'] ?? null) && ! ($movie['adult'] ?? false))
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
            'append_to_response' => 'videos',
        ]));
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

        /** @var array<int, array<string, mixed>> $genreData */
        $genreData = is_array($data['genres'] ?? null) ? $data['genres'] : [];

        $genres = collect($genreData)
            ->pluck('name')
            ->filter(fn (mixed $name): bool => is_string($name) && $name !== '')
            ->take(4)
            ->implode(', ');

        /** @var array<int, array<string, mixed>> $videoData */
        $videoData = is_array(data_get($data, 'videos.results')) ? data_get($data, 'videos.results') : [];

        $video = collect($videoData)
            ->first(fn (mixed $item): bool => is_array($item)
                && ($item['site'] ?? null) === 'YouTube'
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
        if (! is_string($path) || ! preg_match('/^\/[A-Za-z0-9_-]+\.(?:jpg|jpeg|png|webp)$/i', $path)) {
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

        if (! is_string($token) || trim($token) === '') {
            throw new RuntimeException('TMDB_API_READ_TOKEN belum diatur pada server.');
        }

        $response = Http::withToken($token)
            ->acceptJson()
            ->connectTimeout(3)
            ->timeout(10)
            ->get('https://api.themoviedb.org/3/'.$endpoint, $query);

        $response->throw();

        $data = $response->json();

        if (! is_array($data)) {
            throw new RuntimeException('TMDB mengembalikan respons yang tidak valid.');
        }

        return $data;
    }
}
