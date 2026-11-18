<?php

namespace App\Console\Commands;

use App\Services\Payments\OnlinePayments;
use Illuminate\Console\Command;

/**
 * Asks Paystack about payments still waiting, in case its message never
 * arrived. Runs every five minutes.
 *
 *     php artisan wiifarm:check-payments
 */
class CheckPayments extends Command
{
    protected $signature = 'wiifarm:check-payments';

    protected $description = 'Ask Paystack about online payments still waiting for confirmation';

    public function handle(OnlinePayments $payments): int
    {
        $this->info($payments->checkWaiting().' payment(s) checked.');

        return self::SUCCESS;
    }
}
