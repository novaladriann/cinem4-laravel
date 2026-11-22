<?php

namespace Tests\Feature\Admin;

use App\Models\Movie;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MovieManagementTest extends TestCase
{
    use RefreshDatabase;

    private function createMovie(string $title = 'Film Uji'): Movie
    {
        return Movie::query()->create([
            'title' => $title,
            'slug' => 'film-uji-'.str()->random(8),
            'genre' => 'Drama',
            'duration_minute' => 110,
        ]);
    }

    public function test_only_verified_admins_can_manage_movies(): void
    {
        $movie = $this->createMovie();

        $this->get(route('admin.movies.index'))->assertRedirect(route('login'));
        $this->get(route('admin.movies.edit', $movie))->assertRedirect(route('login'));
        $this->patch(route('admin.movies.update', $movie))->assertRedirect(route('login'));

        $user = User::factory()->create();
        $this->actingAs($user)->get(route('admin.movies.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.movies.edit', $movie))->assertForbidden();
        $this->actingAs($user)->patch(route('admin.movies.update', $movie))->assertForbidden();

        $unverifiedAdmin = User::factory()->unverified()->create(['is_admin' => true]);
        $this->actingAs($unverifiedAdmin)->get(route('admin.movies.index'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_verified_admin_can_view_movies_and_edit_form(): void
    {
        $movie = $this->createMovie('Film Admin');
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get(route('admin.movies.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/movies/Index')
                ->has('movies.data', 1)
                ->where('movies.data.0.title', 'Film Admin'));

        $this->actingAs($admin)
            ->get(route('admin.movies.edit', $movie))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/movies/Edit')
                ->where('movie.id_movie', $movie->id_movie));
    }

    public function test_admin_can_update_status_active_flag_and_featured_without_changing_metadata(): void
    {
        $movie = $this->createMovie();
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->patch(route('admin.movies.update', $movie), [
                'status' => 'now_showing',
                'is_active' => false,
                'is_featured' => true,
                'title' => 'Tidak Boleh Ditimpa',
                'tmdb_id' => 9999,
            ])
            ->assertRedirect(route('admin.movies.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('movies', [
            'id_movie' => $movie->id_movie,
            'title' => 'Film Uji',
            'tmdb_id' => null,
            'status' => 'now_showing',
            'is_active' => false,
            'is_featured' => true,
        ]);

        $this->get(route('movies.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('movies/Index')
                ->has('movies.data', 0));
    }

    public function test_invalid_updates_are_rejected_without_changing_the_movie(): void
    {
        $movie = $this->createMovie();
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->from(route('admin.movies.edit', $movie))
            ->patch(route('admin.movies.update', $movie), [
                'status' => 'deleted',
                'is_active' => 'maybe',
                'is_featured' => true,
            ])
            ->assertRedirect(route('admin.movies.edit', $movie))
            ->assertSessionHasErrors(['status', 'is_active']);

        $this->assertDatabaseHas('movies', [
            'id_movie' => $movie->id_movie,
            'status' => 'coming_soon',
            'is_active' => true,
            'is_featured' => false,
        ]);
    }

    public function test_featured_active_movies_appear_first_in_public_catalog(): void
    {
        $regular = $this->createMovie('Reguler');
        $featured = $this->createMovie('Unggulan');
        $featured->update(['is_featured' => true]);

        $this->get(route('movies.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('movies/Index')
                ->has('movies.data', 2)
                ->where('movies.data.0.id_movie', $featured->id_movie)
                ->where('movies.data.1.id_movie', $regular->id_movie)
                ->where('movies.data.0.is_featured', true));
    }
}
