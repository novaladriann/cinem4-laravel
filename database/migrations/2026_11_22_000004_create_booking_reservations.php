<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table): void {
            $table->increments('id_booking');
            $table->foreignId('id_user')->constrained('users', 'id')->restrictOnDelete();
            $table->unsignedInteger('id_schedule');
            $table->unsignedSmallInteger('seat_count');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total_price', 12, 2);
            $table->enum('status', ['pending', 'paid', 'expired', 'cancelled'])->default('pending');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->foreign('id_schedule')->references('id_schedule')->on('schedules')->restrictOnDelete();
            $table->index(['id_schedule', 'status', 'expires_at']);
            $table->index(['id_user', 'created_at']);
        });

        // Immutable seat history. Reusing seats after expiration does not erase booking records.
        Schema::create('booking_seats', function (Blueprint $table): void {
            $table->increments('id_booking_seat');
            $table->unsignedInteger('id_booking');
            $table->unsignedSmallInteger('seat_number');

            $table->foreign('id_booking')->references('id_booking')->on('bookings')->restrictOnDelete();
            $table->unique(['id_booking', 'seat_number']);
        });

        // Only live holds are unique per showtime/seat; released holds disappear from this table.
        Schema::create('seat_locks', function (Blueprint $table): void {
            $table->unsignedInteger('id_schedule');
            $table->unsignedSmallInteger('seat_number');
            $table->unsignedInteger('id_booking');

            $table->primary(['id_schedule', 'seat_number']);
            $table->foreign('id_schedule')->references('id_schedule')->on('schedules')->restrictOnDelete();
            $table->foreign('id_booking')->references('id_booking')->on('bookings')->restrictOnDelete();
            $table->index('id_booking');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seat_locks');
        Schema::dropIfExists('booking_seats');
        Schema::dropIfExists('bookings');
    }
};
