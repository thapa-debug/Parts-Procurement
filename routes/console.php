<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Missed-webhook backstop for Stripe payments: confirms awaiting_payment
// requests whose payment actually succeeded at Stripe (see
// ReconcileStripePayments). Needs the scheduler running in production
// (`php artisan schedule:work` or a cron entry for `schedule:run`).
Schedule::command('payments:reconcile')->everyFifteenMinutes()->withoutOverlapping();
