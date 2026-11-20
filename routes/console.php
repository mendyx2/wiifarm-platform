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

// Every night: the matching bonus for the day just ended (specification section 5.5).
Schedule::command('wiifarm:match')->dailyAt('01:30')->timezone('Africa/Lagos')->withoutOverlapping();

// Every five minutes: ask Paystack about payments still waiting (specification section 6).
Schedule::command('wiifarm:check-payments')->everyFiveMinutes()->withoutOverlapping();

// Every night: cancel public orders not paid in time (specification section 8).
Schedule::command('wiifarm:expire-public-orders')->dailyAt('03:30')->timezone('Africa/Lagos');

// Every day: the leadership bonus for the month just ended, once (specification section 5.7).
Schedule::command('wiifarm:leadership')->dailyAt('02:30')->timezone('Africa/Lagos')->withoutOverlapping();

// Every night: who qualifies for a recognition or repurchase award (specification section 5.8).
Schedule::command('wiifarm:awards')->dailyAt('02:00')->timezone('Africa/Lagos')->withoutOverlapping();

// Every night: release held matching to members who made their autoship, or flush it after 3 missed months (5.6).
Schedule::command('wiifarm:autoship')->dailyAt('03:10')->timezone('Africa/Lagos')->withoutOverlapping();

// Every morning from the 22nd to the 7th: remind members who have not made the month's autoship (5.6).
Schedule::command('wiifarm:autoship-reminders')->dailyAt('09:20')->timezone('Africa/Lagos')->withoutOverlapping();

// Every minute: send what is waiting in the background (member messages), then stop.
// This needs only the scheduler's cron line, not a separate worker process.
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')->everyMinute()->withoutOverlapping();

// Every minute: USDT payments nobody reported, and deposits whose hour has passed (section 6).
Schedule::command('wiifarm:usdt-scan')->everyMinute()->withoutOverlapping();
