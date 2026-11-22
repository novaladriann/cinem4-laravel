<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Cinema;
use App\Models\Movie;
use App\Models\Schedule;
use App\Models\Studio;
use App\Models\User;
use App\Services\SeatReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MidtransSandboxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.midtrans.sandbox_server_key', 'sandbox-test-server-key');
        config()->set('services.midtrans.sandbox_signature_secret_key', 'sandbox-test-signature-secret');
        Http::preventStrayRequests();
    }

    private function pendingBooking(): Booking
    {
        $movie = Movie::query()->create([
            'title' => 'Film Sandbox',
            'slug' => 'film-sandbox',
            'genre' => 'Drama',
            'duration_minute' => 110,
            'status' => 'now_showing',
        ]);
        $cinema = Cinema::query()->create(['name' => 'CINEM4 Sandbox']);
        $studio = Studio::query()->create([
            'id_cinema' => $cinema->id_cinema,
            'name' => 'Studio 1',
            'seat_capacity' => 30,
            'turnaround_minutes' => 15,
        ]);
        $schedule = Schedule::query()->create([
            'id_movie' => $movie->id_movie,
            'id_cinema' => $cinema->id_cinema,
            'id_studio' => $studio->id_studio,
            'studio_name' => 'Studio 1',
            'seat_capacity' => 30,
            'show_date' => '2027-07-12',
            'show_time' => '19:00',
            'price' => '45000.00',
            'booking_close_minutes' => 30,
            'status' => 'open',
        ]);

        $user = User::factory()->create();
        $this->actingAs($user);

        return app(SeatReservationService::class)->hold($schedule, $user->id, [3, 4]);
    }

    private function fakePaymentCreation(): void
    {
        Http::fake([
            'app.sandbox.midtrans.com/snap/v1/transactions' => Http::response([
                'token' => 'test-token',
                'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v4/redirection/test-token',
            ], 201),
        ]);
    }

    public function test_checkout_starts_only_for_its_owner_and_reuses_the_same_order(): void
    {
        $booking = $this->pendingBooking();
        $other = User::factory()->create();

        $this->actingAs($other)
            ->post(route('payments.start', $booking))->assertForbidden();

        $this->actingAs($booking->user);
        $this->fakePaymentCreation();

        $this->post(route('payments.start', $booking))->assertRedirect('https://app.sandbox.midtrans.com/snap/v4/redirection/test-token');
        $booking->refresh();

        $this->assertNotNull($booking->midtrans_order_id);
        $this->assertNotNull($booking->midtrans_redirect_url);
        $this->assertSame(2, DB::table('seat_locks')->count());

        $orderId = $booking->midtrans_order_id;
        $this->post(route('payments.start', $booking))->assertRedirect('https://app.sandbox.midtrans.com/snap/v4/redirection/test-token');

        $this->assertSame($orderId, $booking->fresh()->midtrans_order_id);
        Http::assertSentCount(1);
    }

    public function test_webhook_signature_is_required_and_confirmed_with_midtrans_api(): void
    {
        $booking = $this->pendingBooking();
        $this->fakePaymentCreation();
        $this->post(route('payments.start', $booking));
        $booking->refresh();

        $notification = [
            'order_id' => $booking->midtrans_order_id,
            'status_code' => '200',
            'gross_amount' => '90000.00',
            'transaction_status' => 'settlement',
            'signature_key' => 'bad-signature',
        ];

        $this->postJson(route('midtrans.webhook'), $notification)->assertForbidden();
        $this->assertSame('pending', $booking->fresh()->status);

        Http::fake([
            'api.sandbox.midtrans.com/v2/*/status' => Http::response([
                'order_id' => $booking->midtrans_order_id,
                'gross_amount' => '90000.00',
                'transaction_status' => 'settlement',
                'status_code' => '200',
            ]),
        ]);

        // An API Server Key is no longer accepted as a notification signing secret.
        $notification['signature_key'] = hash('sha512', $booking->midtrans_order_id.'200'.'90000.00'.'sandbox-test-server-key');
        $this->postJson(route('midtrans.webhook'), $notification)->assertForbidden();
        $this->assertSame('pending', $booking->fresh()->status);

        $notification['signature_key'] = hash('sha512', $booking->midtrans_order_id.'200'.'90000.00'.'sandbox-test-signature-secret');

        $this->postJson(route('midtrans.webhook'), $notification)->assertOk();
        $this->postJson(route('midtrans.webhook'), $notification)->assertOk();

        $this->assertSame('paid', $booking->fresh()->status);
        $this->assertDatabaseCount('seat_locks', 2);
        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_expired_payment_releases_seats_but_keeps_booking_history(): void
    {
        $booking = $this->pendingBooking();
        $this->fakePaymentCreation();
        $this->post(route('payments.start', $booking));
        $booking->refresh();

        Http::fake([
            'api.sandbox.midtrans.com/v2/*/status' => Http::response([
                'order_id' => $booking->midtrans_order_id,
                'gross_amount' => '90000.00',
                'transaction_status' => 'expire',
                'status_code' => '200',
            ]),
        ]);

        $this->post(route('payments.refresh', $booking))
            ->assertRedirect(route('bookings.show', $booking));

        $this->assertSame('expired', $booking->fresh()->status);
        $this->assertDatabaseCount('seat_locks', 0);
        $this->assertDatabaseCount('booking_seats', 2);
    }

    public function test_initiated_payment_cannot_be_cancelled_and_is_not_lazily_released(): void
    {
        $booking = $this->pendingBooking();
        $this->fakePaymentCreation();
        $this->post(route('payments.start', $booking));
        $booking->refresh();
        $booking->update(['expires_at' => now()->subMinutes(5)]);

        $this->from(route('bookings.show', $booking))
            ->patch(route('bookings.cancel', $booking))->assertSessionHasErrors('booking');

        app(SeatReservationService::class)->releaseExpired($booking->id_schedule);

        $this->assertSame('pending', $booking->fresh()->status);
        $this->assertDatabaseCount('seat_locks', 2);
    }

    public function test_late_confirmation_creates_review_instead_of_reissuing_reserved_seats(): void
    {
        $booking = $this->pendingBooking();
        $this->fakePaymentCreation();
        $this->post(route('payments.start', $booking));
        $booking->refresh();

        $booking->update(['status' => 'cancelled']);
        DB::table('seat_locks')->where('id_booking', $booking->id_booking)->delete();

        Http::fake([
            'api.sandbox.midtrans.com/v2/*/status' => Http::response([
                'order_id' => $booking->midtrans_order_id,
                'gross_amount' => '90000.00',
                'transaction_status' => 'settlement',
                'status_code' => '200',
            ]),
        ]);

        $this->post(route('payments.refresh', $booking))->assertRedirect();

        $this->assertSame('cancelled', $booking->fresh()->status);
        $this->assertDatabaseHas('payment_reviews', ['id_booking' => $booking->id_booking]);
        $this->assertDatabaseCount('seat_locks', 0);
    }

    public function test_midtrans_server_key_is_never_required_for_bookings_without_checkout(): void
    {
        config()->set('services.midtrans.sandbox_server_key', null);
        $booking = $this->pendingBooking();

        $this->from(route('bookings.show', $booking))
            ->post(route('payments.start', $booking))->assertSessionHasErrors('payment');

        $this->assertNull($booking->fresh()->midtrans_order_id);
        Http::assertNothingSent();
    }
}
