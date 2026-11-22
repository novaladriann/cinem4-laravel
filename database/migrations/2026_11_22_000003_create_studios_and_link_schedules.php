<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('studios', function (Blueprint $table): void {
            $table->increments('id_studio');
            $table->unsignedInteger('id_cinema');
            $table->string('name', 50);
            $table->unsignedInteger('seat_capacity');
            $table->unsignedSmallInteger('turnaround_minutes')->default(15);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('id_cinema')->references('id_cinema')->on('cinemas')->restrictOnDelete();
            $table->unique(['id_cinema', 'name']);
        });

        Schema::table('schedules', function (Blueprint $table): void {
            // Nullable so historical schedules that only store studio_name remain readable.
            $table->unsignedInteger('id_studio')->nullable();
            $table->foreign('id_studio')->references('id_studio')->on('studios')->restrictOnDelete();
            $table->index(['id_studio', 'show_date']);
        });
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table): void {
            $table->dropForeign(['id_studio']);
            $table->dropIndex(['id_studio', 'show_date']);
            $table->dropColumn('id_studio');
        });

        Schema::dropIfExists('studios');
    }
};
