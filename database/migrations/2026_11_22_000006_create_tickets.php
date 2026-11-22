<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('id_booking')->unique();
            $table->string('code', 64)->unique();
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->foreign('id_booking')->references('id_booking')->on('bookings')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
