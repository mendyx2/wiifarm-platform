<?php

namespace App\Console\Commands;

use App\Services\Compensation\Awards;
use Illuminate\Console\Command;

/**
 * The nightly check for new awards (specification sections 5.8, 5.11 and
 * 12). It records who qualifies; an admin approves each award.
 *
 *     php artisan wiifarm:awards
 */
class DetectAwards extends Command
{
    protected $signature = 'wiifarm:awards';

    protected $description = 'Find members who qualify for a recognition or repurchase award';

    public function handle(Awards $awards): int
    {
        $this->info($awards->detect().' new award(s) waiting for an admin.');

        return self::SUCCESS;
    }
}
