<?php

namespace App\Console\Commands;

use App\Services\Pools\PoolPayouts;
use Illuminate\Console\Command;

/**
 * Pays held payouts whose pool line has money again, oldest first. New money
 * already triggers this as it arrives; the hourly run is a safety net.
 *
 *     php artisan wiifarm:pay-held
 */
class PayHeldPayouts extends Command
{
    protected $signature = 'wiifarm:pay-held';

    protected $description = 'Pay held payouts that their pool line can now cover, oldest first';

    public function handle(PoolPayouts $payouts): int
    {
        $paid = $payouts->releaseAll();
        $this->info("{$paid} held payout(s) paid.");

        return self::SUCCESS;
    }
}
