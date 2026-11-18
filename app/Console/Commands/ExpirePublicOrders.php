<?php

namespace App\Console\Commands;

use App\Models\Payments\OnlinePayment;
use App\Models\Shop\Order;
use Illuminate\Console\Command;

/**
 * Cancels public-shop orders not paid within the set number of days
 * (specification section 8). A paid order's code never expires, and an
 * order whose payment Paystack is still confirming is left alone.
 *
 *     php artisan wiifarm:expire-public-orders
 */
class ExpirePublicOrders extends Command
{
    protected $signature = 'wiifarm:expire-public-orders';

    protected $description = 'Cancel public orders that were not paid in time';

    public function handle(): int
    {
        $count = Order::where('kind', Order::PUBLIC)->where('status', Order::PENDING)
            ->where('created_at', '<', now()->subDays((int) config('wiifarm.public_order_days')))
            ->whereDoesntHave('onlinePayments', fn ($q) => $q->where('status', OnlinePayment::PENDING))
            ->update(['status' => Order::CANCELLED, 'updated_at' => now()]);

        $this->info("{$count} unpaid public order(s) cancelled.");

        return self::SUCCESS;
    }
}
