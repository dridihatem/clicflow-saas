<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('invoices:process-recurring')->dailyAt('08:30');

Schedule::command('invoices:send-reminders')->dailyAt('09:00');

Schedule::command('clicflow:send-push-reminders')->everyFifteenMinutes();

Schedule::command('backup:run')->dailyAt('02:00');

Schedule::command('vault:purge-trash')->dailyAt('03:15');
