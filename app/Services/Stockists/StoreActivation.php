<?php

namespace App\Services\Stockists;

use App\Models\Admin;
use App\Models\Pools\Manufacturer;
use App\Models\Stockists\Store;
use App\Models\User;
use App\Services\Admin\Audit;
use App\Services\Ledger\Ledger;
use App\Services\Network\PvClimb;
use App\Services\Pools\PaymentSplit;
use App\Services\Pools\PoolPayouts;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Activating a store once its deposit is received (specification section 9).
 * In one step, once only:
 *
 *  - the deposit is split: half to the manufacturing company for the
 *    products the store receives (₦750 of every ₦1,500); from the other half,
 *    8% (Sub) or 6.5% (Mega) of the deposit for the stockist sponsor bonus,
 *    and the rest to Pool D's contingency fund;
 *  - the stockist sponsor bonus goes to 10 generations, starting with the
 *    store's sponsor. A share with nobody to receive it stays in Pool D's
 *    contingency fund;
 *  - the store PV (3,000 or 10,000) goes 10 levels up the stockist's binary
 *    tree as lifetime PV, and 10 generations up their referral chain. It
 *    counts for awards only, never for matching.
 */
class StoreActivation
{
    public function __construct(
        private readonly Ledger $ledger,
        private readonly PaymentSplit $split,
        private readonly PoolPayouts $payouts,
        private readonly PvClimb $climb,
        private readonly Audit $audit,
    ) {}

    public function activate(Store $store, Admin $admin, string $depositReference): Store
    {
        $depositReference = trim($depositReference);
        if ($depositReference === '') {
            throw new InvalidArgumentException('Give the reference of the deposit payment.');
        }

        return DB::transaction(function () use ($store, $admin, $depositReference) {
            $store = Store::with('owner', 'sponsor')->lockForUpdate()->findOrFail($store->id);
            if ($store->status !== Store::PENDING) {
                throw new InvalidArgumentException('This store has already been activated.');
            }
            if (! $store->owner->isActivated() || $store->owner->isBanned()) {
                throw new InvalidArgumentException('The stockist must be a member in good standing with a paid package.');
            }
            if ($store->kind === Store::SUB && ! $store->mega?->isActive()) {
                throw new InvalidArgumentException('A Sub store needs an active Mega store to collect from.');
            }

            $kind = config("stockists.kinds.{$store->kind}");

            // 1. The deposit.
            $this->split->activation($store->deposit, $store->kind, Manufacturer::firstOrFail(), 'store:'.$store->id, $store);

            // 2. The stockist sponsor bonus, 10 generations.
            $recipient = $store->sponsor;
            foreach ($kind['sponsor_bonus'] as $i => $amount) {
                $generation = $i + 1;
                $key = "store:{$store->id}:sponsor:gen{$generation}";
                $memo = "Stockist sponsor bonus, generation {$generation}, for {$store->name}";

                if ($this->canReceive($recipient)) {
                    $this->payouts->pay('D', 'sponsor_bonus', $this->ledger->memberAccount($recipient, 'bonus.stockist_sponsor'), $amount, 'stockist_sponsor', $key, $memo, $store);
                } else {
                    $this->payouts->toContingency('D', 'sponsor_bonus', $amount, $key, $memo.': nobody to receive it', $store);
                }
                $recipient = $recipient?->sponsor;
            }

            // 3. The store PV, for awards only: 10 levels up the tree, 10 generations up the referral chain.
            $this->climb->credit($store->owner, $kind['store_pv'], PvClimb::SOURCE_STORE, 'pv:store:'.$store->id, $store);
            $this->referralChainPv($store, $kind['store_pv']);

            $store->forceFill(['status' => Store::ACTIVE, 'activated_at' => now(), 'deposit_reference' => $depositReference])->save();
            $this->audit->record($admin, 'stockists.activate', $store, ['status' => Store::PENDING], ['status' => Store::ACTIVE, 'deposit' => $store->deposit], 'Deposit reference: '.$depositReference);

            return $store;
        });
    }

    /** The company account, a banned member or a member without a paid package receives nothing. */
    private function canReceive(?User $member): bool
    {
        return $member !== null && $member->isActivated() && ! $member->isBanned() && ! $member->is(User::company());
    }

    private function referralChainPv(Store $store, int $pv): void
    {
        $lines = [];
        $member = $store->owner->sponsor;
        for ($i = 0; $i < config('stockists.store_pv_generations') && $member !== null; $i++) {
            $lines[] = [$this->ledger->teamStorePvAccount($member), $pv];
            $member = $member->sponsor;
        }

        if ($lines !== []) {
            $lines[] = [$this->ledger->systemAccount('pv.issued'), -$pv * count($lines)];
            $this->ledger->post('pv.store_team', 'pv:store-team:'.$store->id, $lines, "Store PV from {$store->name}", $store);
        }
    }
}
