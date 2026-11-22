<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movies', function (Blueprint $table): void {
            $table->increments('id_movie');
            $table->unsignedInteger('tmdb_id')->nullable()->unique();
            $table->string('title', 150);
            $table->string('slug', 180)->unique();
            $table->string('genre', 150);
            $table->unsignedInteger('duration_minute');
            $table->string('rating_age', 20)->nullable();
            $table->date('release_date')->nullable();
            $table->enum('status', ['now_showing', 'coming_soon'])->default('coming_soon');
            $table->text('synopsis')->nullable();
            $table->string('poster')->nullable();
            $table->string('backdrop')->nullable();
            $table->string('trailer_url')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->boolean('show_in_hero')->default(false);
            $table->unsignedInteger('hero_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'status']);
        });

        Schema::create('cinemas', function (Blueprint $table): void {
            $table->increments('id_cinema');
            $table->string('name', 100);
            $table->string('city', 100)->nullable();
            $table->text('address')->nullable();
            $table->string('image')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('schedules', function (Blueprint $table): void {
            $table->increments('id_schedule');
            $table->unsignedInteger('id_movie');
            $table->unsignedInteger('id_cinema');
            $table->string('studio_name', 50);
            $table->date('show_date');
            $table->time('show_time');
            $table->decimal('price', 10, 2)->default(0);
            $table->unsignedInteger('seat_capacity')->default(40);
            $table->unsignedInteger('booking_close_minutes')->default(30);
            $table->enum('status', ['open', 'closed', 'finished', 'cancelled'])->default('open');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('id_movie')->references('id_movie')->on('movies')->restrictOnDelete();
            $table->foreign('id_cinema')->references('id_cinema')->on('cinemas')->restrictOnDelete();
            $table->unique(['id_cinema', 'studio_name', 'show_date', 'show_time'], 'schedules_studio_start_unique');
            $table->index(['id_movie', 'show_date']);
            $table->index(['id_cinema', 'show_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedules');
        Schema::dropIfExists('cinemas');
        Schema::dropIfExists('movies');
    }
};
