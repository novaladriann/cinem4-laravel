<?php

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
        Route::get('/movies/import', [TmdbMovieController::class, 'index'])->name('movies.import');
        Route::post('/movies/import/{tmdbId}', [TmdbMovieController::class, 'store'])
            ->whereNumber('tmdbId')
            ->name('movies.store');
    });

require __DIR__.'/settings.php';
