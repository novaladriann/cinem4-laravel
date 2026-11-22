<?php

namespace Tests\Feature\Admin;

use App\Models\Booking;
use App\Models\Cinema;
use App\Models\Movie;
use App\Models\Schedule;
use App\Models\Studio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CinemaScheduleManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    /**
     * @return array{movie: Movie, cinema: Cinema, studio: Studio}
     */
    private function catalog(): array
    {
        $movie = Movie::query()->create([
            'title' => 'Film Operasional',
            'slug' => 'film-operasional',
            'genre' => 'Drama',
            'duration_minute' => 120,
            'status' => 'now_showing',
            'is_active' => true,
        ]);
        $cinema = Cinema::query()->create(['name' => 'CINEM4 Uji', 'city' => 'Jakarta']);
        $studio = Studio::query()->create([
            'id_cinema' => $cinema->id_cinema,
            'name' => 'Studio 1',
            'seat_capacity' => 64,
            'turnaround_minutes' => 20,
        ]);

        return compact('movie', 'cinema', 'studio');
    }

    /**
     * @param  array{movie: Movie, cinema: Cinema, studio: Studio}  $catalog
     * @return array<string, int|string>
     */
    private function show(array $catalog, string $time = '18:00'): array
    {
        return [
            'id_movie' => $catalog['movie']->id_movie,
            'id_cinema' => $catalog['cinema']->id_cinema,
            'id_studio' => $catalog['studio']->id_studio,
            'show_date' => '2027-03-12',
            'show_time' => $time,
            'price' => 45000,
        ];
    }

    public function test_regular_accounts_cannot_manage_cinemas_and_schedules(): void
    {
        $this->get(route('admin.cinemas.index'))->assertRedirect(route('login'));
        $this->get(route('admin.schedules.index'))->assertRedirect(route('login'));
        $this->post(route('admin.cinemas.store'))->assertRedirect(route('login'));
        $this->post(route('admin.studios.store'))->assertRedirect(route('login'));
        $this->post(route('admin.schedules.store'))->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create())
            ->get(route('admin.cinemas.index'))->assertForbidden();

        $this->get(route('admin.schedules.index'))->assertForbidden();
        $this->post(route('admin.cinemas.store'))->assertForbidden();
        $this->post(route('admin.studios.store'))->assertForbidden();
        $this->post(route('admin.schedules.store'))->assertForbidden();
    }

    public function test_admin_can_add_cinema_and_studio_but_duplicate_name_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.cinemas.store'), [
                'name' => 'CINEM4 Utama',
                'city' => 'Jakarta',
                'address' => 'Alamat uji',
            ])->assertRedirect(route('admin.cinemas.index'));

        $cinema = Cinema::query()->firstOrFail();
        $payload = [
            'id_cinema' => $cinema->id_cinema,
            'name' => 'Studio 1',
            'seat_capacity' => 60,
            'turnaround_minutes' => 15,
        ];

        $this->post(route('admin.studios.store'), $payload)
            ->assertRedirect(route('admin.cinemas.index'));

        $this->from(route('admin.cinemas.index'))
            ->post(route('admin.studios.store'), $payload)
            ->assertSessionHasErrors('name');

        $this->assertDatabaseCount('cinemas', 1);
        $this->assertDatabaseCount('studios', 1);
        $this->get(route('admin.cinemas.index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/cinemas/Index')
                ->has('cinemas', 1)
                ->has('cinemas.0.studios', 1));
    }

    public function test_schedule_creation_snapshots_capacity_and_rejects_overlapping_showtimes(): void
    {
        $catalog = $this->catalog();

        $this->actingAs($this->admin())
            ->post(route('admin.schedules.store'), $this->show($catalog))
            ->assertRedirect(route('admin.schedules.index'));

        $this->assertDatabaseHas('schedules', [
            'id_studio' => $catalog['studio']->id_studio,
            'studio_name' => 'Studio 1',
            'seat_capacity' => 64,
            'price' => 45000,
        ]);

        $this->from(route('admin.schedules.index'))
            ->post(route('admin.schedules.store'), $this->show($catalog, '20:15'))
            ->assertSessionHasErrors('show_time');

        $this->assertDatabaseCount('schedules', 1);

        $this->post(route('admin.schedules.store'), $this->show($catalog, '20:20'))
            ->assertRedirect(route('admin.schedules.index'));

        $this->assertDatabaseCount('schedules', 2);
        $this->get(route('admin.schedules.index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/schedules/Index')
                ->has('schedules.data', 2));
    }

    public function test_schedule_cannot_use_inactive_movie_or_studio_from_another_cinema(): void
    {
        $catalog = $this->catalog();
        $admin = $this->admin();
        $catalog['movie']->update(['status' => 'coming_soon']);

        $this->actingAs($admin)
            ->from(route('admin.schedules.index'))
            ->post(route('admin.schedules.store'), $this->show($catalog))
            ->assertSessionHasErrors('id_movie');

        $catalog['movie']->update(['status' => 'now_showing']);
        $otherCinema = Cinema::query()->create(['name' => 'Cabang Lain']);

        $this->post(route('admin.schedules.store'), [
            ...$this->show($catalog),
            'id_cinema' => $otherCinema->id_cinema,
        ])->assertSessionHasErrors('id_studio');

        $this->assertDatabaseCount('schedules', 0);
    }

    public function test_cancellation_preserves_schedule_record(): void
    {
        $catalog = $this->catalog();
        $this->actingAs($this->admin())
            ->post(route('admin.schedules.store'), $this->show($catalog));

        $schedule = Schedule::query()->firstOrFail();

        $this->patch(route('admin.schedules.cancel', $schedule))
            ->assertRedirect();

        $this->assertDatabaseHas('schedules', [
            'id_schedule' => $schedule->id_schedule,
            'status' => 'cancelled',
            'is_active' => false,
        ]);
    }

    public function test_cancelling_a_showtime_releases_pending_reservations(): void
    {
        $catalog = $this->catalog();
        $admin = $this->admin();
        $customer = User::factory()->create();

        $this->actingAs($admin)->post(route('admin.schedules.store'), $this->show($catalog));
        $schedule = Schedule::query()->firstOrFail();
        $this->actingAs($customer)->post(route('bookings.store', $schedule), ['seats' => [4]]);
        $this->assertDatabaseCount('seat_locks', 1);

        $this->actingAs($admin)->patch(route('admin.schedules.cancel', $schedule))->assertRedirect();

        $this->assertDatabaseHas('bookings', ['id_schedule' => $schedule->id_schedule, 'status' => 'cancelled']);
        $this->assertDatabaseCount('seat_locks', 0);
        $this->assertDatabaseCount('booking_seats', 1);
    }

    public function test_paid_reservations_block_showtime_cancellation(): void
    {
        $catalog = $this->catalog();
        $admin = $this->admin();
        $customer = User::factory()->create();

        $this->actingAs($admin)->post(route('admin.schedules.store'), $this->show($catalog));
        $schedule = Schedule::query()->firstOrFail();
        $this->actingAs($customer)->post(route('bookings.store', $schedule), ['seats' => [5]]);
        Booking::query()->firstOrFail()->update(['status' => 'paid', 'expires_at' => null]);

        $this->actingAs($admin)->from(route('admin.schedules.index'))
            ->patch(route('admin.schedules.cancel', $schedule))
            ->assertSessionHasErrors('schedule');

        $this->assertSame('open', $schedule->fresh()->status);
        $this->assertDatabaseCount('seat_locks', 1);
    }
}
