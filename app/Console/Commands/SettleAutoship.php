<?php

namespace App\Console\Commands;

use App\Services\Compensation\Autoship;
use Illuminate\Console\Command;

/**
 * Releases held matching to members who made their autoship, and flushes it
 * to Pool A's Unclaimed Bonus Reserve after 3 missed months in a row
 * (specification 5.6). Runs every night; also runs for a member when they buy.
 *
 *     php artisan wiifarm:autoship
 */
class SettleAutoship extends Command
{
    protected $signature = 'wiifarm:autoship';

    protected $description = 'Release or flush held autoship shares of the matching bonus';

    public function handle(Autoship $autoship): int
    {
        $this->info($autoship->settleAll().' autoship settlement(s) made.');

        return self::SUCCESS;
    }
}
