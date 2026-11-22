<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Runs only when Laravel's scheduler is active; failures are logged without releasing unknown payments.
Schedule::command('payments:reconcile')->everyFiveMinutes()->withoutOverlapping();
