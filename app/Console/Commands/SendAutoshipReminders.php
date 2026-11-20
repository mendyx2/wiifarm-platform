<?php

namespace App\Console\Commands;

use App\Services\Compensation\AutoshipReminders;
use Illuminate\Console\Command;

/**
 * Emails autoship reminders from the 4th week of a month to the 7th of the
 * next (specification 5.6). Runs every morning; each reminder is sent once.
 *
 *     php artisan wiifarm:autoship-reminders
 */
class SendAutoshipReminders extends Command
{
    protected $signature = 'wiifarm:autoship-reminders';

    protected $description = 'Email autoship reminders to members who have not made the month\'s autoship';

    public function handle(AutoshipReminders $reminders): int
    {
        $this->info($reminders->sendAll().' autoship reminder(s) sent.');

        return self::SUCCESS;
    }
}
