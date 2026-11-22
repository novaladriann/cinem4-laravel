<?php

use App\Http\Controllers\Admin\CinemaManagementController;
use App\Http\Controllers\Admin\MovieManagementController;
use App\Http\Controllers\Admin\ScheduleManagementController;
use App\Http\Controllers\Admin\StudioManagementController;
use App\Http\Controllers\Admin\TicketVerificationController;
use App\Http\Controllers\Admin\TmdbMovieController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\MidtransPaymentController;
use App\Http\Controllers\MovieCatalogController;
use App\Http\Controllers\ShowtimeController;
use App\Http\Controllers\TicketController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');
Route::get('/movies', MovieCatalogController::class)->name('movies.index');
Route::get('/showtimes', [ShowtimeController::class, 'index'])->name('showtimes.index');
Route::get('/showtimes/{schedule}', [ShowtimeController::class, 'show'])->name('showtimes.show');

// Midtrans callback uses signed request plus server-side status lookup, not browser sessions.
Route::post('/webhooks/midtrans', [MidtransPaymentController::class, 'webhook'])
    ->middleware('throttle:120,1')->name('midtrans.webhook');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
    Route::post('/showtimes/{schedule}/book', [BookingController::class, 'store'])->middleware('throttle:10,1')->name('bookings.store');
    Route::get('/my-bookings', [BookingController::class, 'index'])->name('bookings.index');
    Route::get('/my-bookings/{booking}', [BookingController::class, 'show'])->name('bookings.show');
    Route::get('/my-bookings/{booking}/finish', [MidtransPaymentController::class, 'finish'])->middleware('throttle:10,1')->name('payments.finish');
    Route::get('/my-bookings/{booking}/ticket-qr', [TicketController::class, 'qr'])->name('tickets.qr');
    Route::post('/my-bookings/{booking}/pay', [MidtransPaymentController::class, 'start'])->middleware('throttle:5,1')->name('payments.start');
    Route::post('/my-bookings/{booking}/refresh', [MidtransPaymentController::class, 'refresh'])->middleware('throttle:10,1')->name('payments.refresh');
    Route::patch('/my-bookings/{booking}/cancel', [BookingController::class, 'cancel'])->name('bookings.cancel');
});

Route::middleware(['auth', 'verified', 'can:manage-movies', 'throttle:30,1'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function (): void {
        Route::get('/tickets/{code}', [TicketVerificationController::class, 'show'])->name('tickets.verify');
        Route::post('/tickets/{code}/redeem', [TicketVerificationController::class, 'redeem'])->name('tickets.redeem');
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
