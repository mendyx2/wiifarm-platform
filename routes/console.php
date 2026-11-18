<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Every night: check that the books balance (specification section 1).
Schedule::command('wiifarm:ledger-check')->dailyAt('02:15');

// Every hour: pay held payouts their pool line can now cover (specification section 7).
Schedule::command('wiifarm:pay-held')->hourlyAt(7);
