<?php

use App\Http\Controllers\Admin\CinemaManagementController;
use App\Http\Controllers\Admin\MovieManagementController;
use App\Http\Controllers\Admin\ScheduleManagementController;
use App\Http\Controllers\Admin\StudioManagementController;
use App\Http\Controllers\Admin\TmdbMovieController;
use App\Http\Controllers\MovieCatalogController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');
Route::get('/movies', MovieCatalogController::class)->name('movies.index');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

Route::middleware(['auth', 'verified', 'can:manage-movies', 'throttle:30,1'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function (): void {
        Route::get('/cinemas', [CinemaManagementController::class, 'index'])->name('cinemas.index');
        Route::post('/cinemas', [CinemaManagementController::class, 'store'])->name('cinemas.store');
        Route::patch('/cinemas/{cinema}', [CinemaManagementController::class, 'update'])->name('cinemas.update');
        Route::post('/studios', [StudioManagementController::class, 'store'])->name('studios.store');
        Route::patch('/studios/{studio}', [StudioManagementController::class, 'update'])->name('studios.update');
        Route::get('/schedules', [ScheduleManagementController::class, 'index'])->name('schedules.index');
        Route::post('/schedules', [ScheduleManagementController::class, 'store'])->name('schedules.store');
        Route::patch('/schedules/{schedule}/cancel', [ScheduleManagementController::class, 'cancel'])->name('schedules.cancel');
        Route::get('/movies', [MovieManagementController::class, 'index'])->name('movies.index');
        Route::get('/movies/{movie}/edit', [MovieManagementController::class, 'edit'])->name('movies.edit');
        Route::patch('/movies/{movie}', [MovieManagementController::class, 'update'])->name('movies.update');
        Route::get('/movies/import', [TmdbMovieController::class, 'index'])->name('movies.import');
        Route::post('/movies/import/{tmdbId}', [TmdbMovieController::class, 'store'])
            ->whereNumber('tmdbId')
            ->name('movies.store');
    });

require __DIR__.'/settings.php';
