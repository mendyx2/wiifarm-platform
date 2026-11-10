<?php

namespace App\Services\Network;

use App\Models\Package;
use App\Models\PackageChange;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * A member moves to a higher package by paying the difference in price
 * (specification section 4). The PV difference climbs the tree, and the new
 * package's rates and caps apply from then on. No downgrades; a package an
 * admin marked "no upgrades" cannot be upgraded from.
 *
 * The price difference to pay is price(new) − price(current). The same
 * payment upgrades once only.
 */
class Upgrade
{
    public function __construct(private readonly PvClimb $climb) {}

    public function priceDifference(User $member, Package $to): int
    {
        return $to->price - ($member->package?->price ?? 0);
    }

    public function apply(User $member, Package $to, string $paymentKey): PackageChange
    {
        if ($done = PackageChange::where('payment_key', $paymentKey)->first()) {
            if ($done->user_id !== $member->id || $done->kind !== PackageChange::UPGRADE || $done->to_package_id !== $to->id) {
                throw new PlacementException("The payment [{$paymentKey}] was already used for something else.");
            }

            return $done;
        }

        return DB::transaction(function () use ($member, $to, $paymentKey) {
            $member = User::with('package')->lockForUpdate()->findOrFail($member->id);
            $from = $member->package;

            if (! $member->isActivated() || $from === null) {
                throw new PlacementException('Only a member with a paid package can upgrade.');
            }
            if ($member->isBanned()) {
                throw new PlacementException('A banned member cannot upgrade.');
            }
            if (! $from->upgradable) {
                throw new PlacementException("The {$from->name} package cannot be upgraded.");
            }
            if (! $to->is_active) {
                throw new PlacementException("The {$to->name} package is not on offer.");
            }
            if ($to->pv <= $from->pv) {
                throw new PlacementException("{$to->name} is not higher than {$from->name}. Downgrades are not allowed.");
            }

            $member->forceFill(['package_id' => $to->id])->save();

            $change = PackageChange::create([
                'user_id' => $member->id,
                'kind' => PackageChange::UPGRADE,
                'from_package_id' => $from->id,
                'to_package_id' => $to->id,
                'payment_key' => $paymentKey,
            ]);

            $this->climb->credit($member, ($to->pv - $from->pv) * 100, PvClimb::SOURCE_UPGRADE, 'pv:upgrade:'.$change->id, $change);

            return $change;
        });
    }
}
