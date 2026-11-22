<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Cinema;
use App\Models\Movie;
use App\Models\Schedule;
use App\Models\Studio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SeatBookingTest extends TestCase
{
    use RefreshDatabase;

    private function schedule(): Schedule
    {
        $movie = Movie::query()->create([
            'title' => 'Film Booking',
            'slug' => 'film-booking',
            'genre' => 'Drama',
            'duration_minute' => 120,
            'status' => 'now_showing',
        ]);

        $cinema = Cinema::query()->create(['name' => 'CINEM4 Booking']);
        $studio = Studio::query()->create([
            'id_cinema' => $cinema->id_cinema,
            'name' => 'Studio 1',
            'seat_capacity' => 40,
            'turnaround_minutes' => 15,
        ]);

        return Schedule::query()->create([
            'id_movie' => $movie->id_movie,
            'id_cinema' => $cinema->id_cinema,
            'id_studio' => $studio->id_studio,
            'studio_name' => $studio->name,
            'seat_capacity' => 40,
            'show_date' => '2027-03-12',
            'show_time' => '19:00',
            'price' => '45000.00',
            'booking_close_minutes' => 30,
            'status' => 'open',
        ]);
    }

    public function test_public_can_browse_showtimes_but_login_is_required_to_hold(): void
    {
        $schedule = $this->schedule();

        $this->get(route('showtimes.index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('showtimes/Index')
                ->has('schedules', 1));

        $this->get(route('showtimes.show', $schedule))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('showtimes/Show')
                ->where('schedule.seat_capacity', 40)
                ->where('occupiedSeats', []));

        $this->post(route('bookings.store', $schedule), ['seats' => [1]])
            ->assertRedirect(route('login'));
    }

    public function test_customer_can_hold_seats_and_second_customer_cannot_take_them(): void
    {
        $schedule = $this->schedule();
        $first = User::factory()->create();
        $second = User::factory()->create();

        $this->actingAs($first)
            ->post(route('bookings.store', $schedule), ['seats' => [1, 2]])
            ->assertRedirect();

        $booking = Booking::query()->firstOrFail();

        $this->assertDatabaseHas('bookings', [
            'id_user' => $first->id,
            'id_schedule' => $schedule->id_schedule,
            'status' => 'pending',
            'seat_count' => 2,
            'unit_price' => 45000,
            'total_price' => 90000,
        ]);
        $this->assertDatabaseCount('booking_seats', 2);
        $this->assertDatabaseCount('seat_locks', 2);

        $this->actingAs($second)
            ->from(route('showtimes.show', $schedule))
            ->post(route('bookings.store', $schedule), ['seats' => [2]])
            ->assertSessionHasErrors('seats');

        $this->assertDatabaseCount('bookings', 1);

        $this->get(route('bookings.show', $booking))->assertForbidden();
        $this->actingAs($first)->get(route('bookings.show', $booking))->assertOk();
    }

    public function test_invalid_seats_are_rejected(): void
    {
        $schedule = $this->schedule();
        $this->actingAs(User::factory()->create());

        foreach ([[1, 1], [0], [41], [1, 2, 3, 4, 5, 6, 7]] as $seats) {
            $this->from(route('showtimes.show', $schedule))
                ->post(route('bookings.store', $schedule), ['seats' => $seats])
                ->assertSessionHasErrors();
        }

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_expired_holds_release_seats_without_erasing_booking_history(): void
    {
        $schedule = $this->schedule();
        $first = User::factory()->create();
        $second = User::factory()->create();

        $this->actingAs($first)->post(route('bookings.store', $schedule), ['seats' => [3]]);
        $oldBooking = Booking::query()->firstOrFail();
        $oldBooking->update(['expires_at' => now()->subMinute()]);

        $this->actingAs($second)
            ->post(route('bookings.store', $schedule), ['seats' => [3]])
            ->assertRedirect();

        $this->assertDatabaseCount('bookings', 2);
        $this->assertDatabaseCount('booking_seats', 2);
        $this->assertDatabaseCount('seat_locks', 1);
        $this->assertSame('expired', $oldBooking->fresh()->status);
    }

    public function test_cancelling_pending_booking_releases_seats_and_ownership_is_enforced(): void
    {
        $schedule = $this->schedule();
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($owner)->post(route('bookings.store', $schedule), ['seats' => [6]]);
        $booking = Booking::query()->firstOrFail();

        $this->actingAs($other)->patch(route('bookings.cancel', $booking))->assertForbidden();

        $this->actingAs($owner)->patch(route('bookings.cancel', $booking))
            ->assertRedirect(route('bookings.show', $booking));

        $this->assertSame('cancelled', $booking->fresh()->status);
        $this->assertDatabaseCount('seat_locks', 0);
        $this->assertDatabaseCount('booking_seats', 1);
    }

    public function test_paid_seats_remain_locked_and_paid_booking_cannot_be_cancelled_by_customer(): void
    {
        $schedule = $this->schedule();
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('bookings.store', $schedule), ['seats' => [8]]);
        $booking = Booking::query()->firstOrFail();

        $booking->update(['status' => 'paid', 'expires_at' => null]);

        $this->from(route('bookings.show', $booking))
            ->patch(route('bookings.cancel', $booking))->assertSessionHasErrors('booking');

        $this->assertDatabaseCount('seat_locks', 1);
    }

    public function test_cancelled_showtime_is_not_bookable(): void
    {
        $schedule = $this->schedule();
        $schedule->update(['status' => 'cancelled', 'is_active' => false]);
        $this->get(route('showtimes.show', $schedule))->assertNotFound();

        $this->actingAs(User::factory()->create())
            ->from(route('showtimes.index'))
            ->post(route('bookings.store', $schedule), ['seats' => [1]])
            ->assertSessionHasErrors('seats');
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_waiting_reservations_do_not_count_as_paid_tickets(): void
    {
        $schedule = $this->schedule();
        $this->actingAs(User::factory()->create())
            ->post(route('bookings.store', $schedule), ['seats' => [9]]);

        $this->assertDatabaseMissing('bookings', ['status' => 'paid']);
        $this->assertSame(1, DB::table('seat_locks')->count());
    }
}
