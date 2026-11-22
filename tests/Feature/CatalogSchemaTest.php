<?php

namespace Tests\Feature;

use App\Models\Cinema;
use App\Models\Movie;
use App\Models\Schedule;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CatalogSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_tables_and_legacy_primary_keys_exist(): void
    {
        $this->assertTrue(Schema::hasColumns('movies', ['id_movie', 'tmdb_id', 'slug']));
        $this->assertTrue(Schema::hasColumns('cinemas', ['id_cinema', 'name']));
        $this->assertTrue(Schema::hasColumns('schedules', ['id_schedule', 'id_movie', 'id_cinema']));
    }

    public function test_a_schedule_belongs_to_its_movie_and_cinema(): void
    {
        $movie = Movie::create([
            'tmdb_id' => 123456,
            'title' => 'Film Uji CINEM4',
            'slug' => 'film-uji-cinem4',
            'genre' => 'Drama',
            'duration_minute' => 110,
        ]);
        $cinema = Cinema::create(['name' => 'Bioskop Uji CINEM4']);
        $schedule = Schedule::create([
            'id_movie' => $movie->id_movie,
            'id_cinema' => $cinema->id_cinema,
            'studio_name' => 'REGULAR',
            'show_date' => '2027-01-10',
            'show_time' => '18:30:00',
            'price' => 45000,
        ]);

        $this->assertSame($movie->id_movie, $schedule->movie->id_movie);
        $this->assertSame($cinema->id_cinema, $schedule->cinema->id_cinema);
        $this->assertCount(1, $movie->schedules);
        $this->assertSame('45000.00', $schedule->price);
    }

    public function test_same_studio_cannot_have_duplicate_start_time(): void
    {
        $movie = Movie::create([
            'title' => 'Film Contoh',
            'slug' => 'film-contoh',
            'genre' => 'Drama',
            'duration_minute' => 110,
        ]);
        $cinema = Cinema::create(['name' => 'Bioskop Contoh']);
        $schedule = [
            'id_movie' => $movie->id_movie,
            'id_cinema' => $cinema->id_cinema,
            'studio_name' => 'Studio 1',
            'show_date' => '2027-01-10',
            'show_time' => '18:30:00',
            'price' => 45000,
        ];
        Schedule::create($schedule);

        $this->expectException(QueryException::class);
        Schedule::create($schedule);
    }
}
