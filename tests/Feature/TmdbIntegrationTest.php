<?php

namespace Tests\Feature;

use App\Models\Movie;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TmdbIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.tmdb.token', 'test-token');
        Cache::flush();
        Http::preventStrayRequests();
    }

    public function test_public_catalog_only_shows_active_movies(): void
    {
        Movie::create(['title' => 'Aktif', 'slug' => 'aktif', 'genre' => 'Drama', 'duration_minute' => 90]);
        Movie::create(['title' => 'Nonaktif', 'slug' => 'nonaktif', 'genre' => 'Drama', 'duration_minute' => 100, 'is_active' => false]);

        $this->get('/movies')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('movies/Index')
            ->has('movies.data', 1)
            ->where('movies.data.0.title', 'Aktif'));
    }

    public function test_guests_and_regular_users_cannot_import(): void
    {
        $this->get('/admin/movies/import')->assertRedirect(route('login'));
        $this->post('/admin/movies/import/100')->assertRedirect(route('login'));

        $user = User::factory()->create();
        $this->actingAs($user)->get('/admin/movies/import')->assertForbidden();
        $this->actingAs($user)->post('/admin/movies/import/100')->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_admin_can_search_and_import_once(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Http::fake([
            'api.themoviedb.org/3/search/movie*' => Http::response([
                'results' => [['id' => 100, 'title' => 'Film Uji', 'adult' => false, 'poster_path' => '/poster123.jpg']],
            ]),
            'api.themoviedb.org/3/movie/100*' => Http::response([
                'id' => 100,
                'title' => 'Film Uji',
                'adult' => false,
                'runtime' => 110,
                'release_date' => '2026-11-20',
                'genres' => [['name' => 'Drama']],
                'poster_path' => '/poster123.jpg',
                'videos' => ['results' => [['site' => 'YouTube', 'type' => 'Trailer', 'key' => 'abcdefghijk']]],
            ]),
        ]);

        $this->actingAs($admin)->get('/admin/movies/import?q=Film%20Uji')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/movies/Import')
                ->has('results', 1)
                ->where('results.0.id', 100));

        $this->actingAs($admin)->post('/admin/movies/import/100')->assertRedirect();
        $this->actingAs($admin)->post('/admin/movies/import/100')->assertRedirect();

        $this->assertDatabaseCount('movies', 1);
        $this->assertDatabaseHas('movies', [
            'tmdb_id' => 100,
            'slug' => 'film-uji-100',
            'duration_minute' => 110,
            'genre' => 'Drama',
            'status' => 'coming_soon',
            'trailer_url' => 'https://www.youtube.com/watch?v=abcdefghijk',
        ]);
        Http::assertSentCount(2);
    }

    public function test_adult_content_is_rejected(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Http::fake(['api.themoviedb.org/*' => Http::response(['id' => 200, 'title' => 'Adult', 'adult' => true])]);

        $this->actingAs($admin)->from('/admin/movies/import')
            ->post('/admin/movies/import/200')
            ->assertRedirect('/admin/movies/import')
            ->assertSessionHasErrors('tmdb');

        $this->assertDatabaseCount('movies', 0);
    }

    public function test_tmdb_failure_does_not_save_incomplete_movie(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Http::fake(['api.themoviedb.org/*' => Http::response(['status_message' => 'Unauthorized'], 401)]);

        $this->actingAs($admin)->from('/admin/movies/import')
            ->post('/admin/movies/import/201')
            ->assertSessionHasErrors('tmdb');

        $this->assertDatabaseCount('movies', 0);
    }

    public function test_admin_cannot_be_mass_assigned(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $user->fill(['is_admin' => true])->save();

        $this->assertFalse($user->fresh()->is_admin);
    }
}
