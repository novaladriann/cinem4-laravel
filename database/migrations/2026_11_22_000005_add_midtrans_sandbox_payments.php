<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->string('midtrans_order_id', 55)->nullable()->unique();
            $table->text('midtrans_redirect_url')->nullable();
            $table->timestamp('midtrans_started_at')->nullable();
        });

        Schema::create('payment_reviews', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('id_booking')->unique();
            $table->string('midtrans_status', 50);
            $table->string('reason', 150);
            $table->timestamps();
            $table->foreign('id_booking')->references('id_booking')->on('bookings')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_reviews');
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropUnique(['midtrans_order_id']);
            $table->dropColumn(['midtrans_order_id', 'midtrans_redirect_url', 'midtrans_started_at']);
        });
    }
};
