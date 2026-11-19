<?php

namespace App\Services\Compensation;

use App\Models\Package;
use App\Models\Shop\Order;
use App\Models\User;
use App\Services\Ledger\Ledger;
use App\Services\Pools\PoolPayouts;
use App\Support\Money;

/**
 * The unilevel (re-purchase) bonus, paid the moment a member buys products
 * (specification 5.10; Revised Baseline Rules: "calculated in real time on
 * every member repurchase", the owner, 19 November 2026).
 *
 * The 15 generations above the buyer in the referral chain each earn a share
 * of the PV value (PV × ₦600) of the order: 7%, 5%, 4%, 4%, 3%, 3%, 3%, 2%,
 * 2%, 2%, then 1% for generations 11 to 15 (40% in all). Each upline is paid
 * only on the generations their package reaches (section 4).
 *
 * A share nobody can receive (a missing generation, a package that does not
 * reach it, a banned or unpaid member, the company account) is not passed on:
 * it stays with the cooperative in the repurchase ledger's (Pool C's)
 * contingency fund, and the generation still counts. Shares come from Pool
 * C's unilevel line; when it and its contingency fund are short, a share is
 * held until they refill (section 7). Each order pays once.
 */
class Unilevel
{
    public const GENERATIONS = 15;

    public function __construct(private readonly Ledger $ledger, private readonly PoolPayouts $payouts) {}

    public function forOrder(Order $order, User $buyer): void
    {
        $value = intdiv($order->pv * Package::PV_VALUE_KOBO, 100);   // PV is in hundredths
        $company = User::company();
        $upline = $buyer->sponsor;

        foreach (config('compensation.unilevel') as $i => $rate) {
            $generation = $i + 1;
            $share = Money::share($value, $rate);
            $key = "unilevel:order:{$order->id}:g{$generation}";
            $memo = "Unilevel bonus, generation {$generation}: order {$order->number} by @{$buyer->username}";

            if ($share > 0) {
                $eligible = $upline !== null && ! $upline->is($company) && $upline->isActivated() && ! $upline->isBanned()
                    && ($upline->package?->unilevel_generations ?? 0) >= $generation;
                if ($eligible) {
                    $this->payouts->pay('C', 'unilevel', $this->ledger->memberAccount($upline, 'bonus.unilevel'), $share, 'unilevel', $key, $memo, $order);
                } else {
                    $this->payouts->toContingency('C', 'unilevel', $share, $key, $memo.': nobody to receive it', $order);
                }
            }
            $upline = $upline?->sponsor;
        }
    }
}
