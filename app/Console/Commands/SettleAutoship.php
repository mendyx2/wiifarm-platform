<?php

namespace App\Console\Commands;

use App\Services\Compensation\Autoship;
use Illuminate\Console\Command;

/**
 * Releases held matching to members who made their autoship, and keeps it
 * for the cooperative once a month's first-week grace has passed without it
 * (specification 5.6). Runs every night; also runs for a member when they buy.
 *
 *     php artisan wiifarm:autoship
 */
class SettleAutoship extends Command
{
    protected $signature = 'wiifarm:autoship';

    protected $description = 'Release or keep held autoship shares of the matching bonus';

    public function handle(Autoship $autoship): int
    {
        $this->info($autoship->settleAll().' autoship settlement(s) made.');

        return self::SUCCESS;
    }
}
