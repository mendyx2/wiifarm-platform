<?php

namespace App\Console\Commands;

use App\Services\Usdt\TronGridUnavailable;
use App\Services\Usdt\UsdtDeposits;
use Illuminate\Console\Command;

/**
 * Finds USDT payments to Wiifarm's address that nobody reported, and closes
 * deposits whose hour has passed (specification section 6). Every minute.
 *
 *     php artisan wiifarm:usdt-scan
 */
class ScanUsdt extends Command
{
    protected $signature = 'wiifarm:usdt-scan';

    protected $description = 'Look for USDT payments to Wiifarm\'s address';

    public function handle(UsdtDeposits $usdt): int
    {
        try {
            $this->info($usdt->scan().' USDT payment(s) recorded.');
        } catch (TronGridUnavailable $e) {
            $this->warn($e->getMessage().' Nothing was decided; the next run tries again.');
        }

        return self::SUCCESS;
    }
}
