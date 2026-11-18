<?php

namespace App\Services\Network;

use App\Models\Package;
use App\Models\PackageChange;
use App\Models\Pools\Manufacturer;
use App\Models\TreePosition;
use App\Models\User;
use App\Services\Compensation\JoiningBonuses;
use App\Services\Pools\PaymentSplit;
use App\Services\Shop\Orders;
use Illuminate\Support\Facades\DB;

/**
 * A member's package is paid: they become a member (specification section 2).
 * In one transaction: placed in the tree, given the package, the package's
 * PV climbs to every upline, the packs' order is created, the payment is
 * split (manufacturer and Pool A), and the direct, indirect and key-in
 * bonuses are paid. If anything fails, nothing happens.
 *
 * $paymentKey names the payment (for example "paystack:ref_123" or
 * "epin:4567"). The same payment activates once only; calling again with it
 * changes nothing.
 */
class Registration
{
    public function __construct(
        private readonly Tree $tree,
        private readonly PvClimb $climb,
        private readonly Orders $orders,
        private readonly PaymentSplit $split,
        private readonly JoiningBonuses $bonuses,
    ) {}

    public function activate(User $member, Package $package, string $paymentKey, ?User $keyedInBy = null): PackageChange
    {
        if ($done = PackageChange::where('payment_key', $paymentKey)->first()) {
            if ($done->user_id !== $member->id || $done->kind !== PackageChange::JOIN || $done->to_package_id !== $package->id) {
                throw new PlacementException("The payment [{$paymentKey}] was already used for something else.");
            }

            return $done; // already done: nothing more to do
        }

        if ($member->isActivated() || $member->position()->exists()) {
            throw new PlacementException("@{$member->username} is already a member with a paid package.");
        }
        if ($member->isBanned()) {
            throw new PlacementException("@{$member->username} is banned and cannot be activated.");
        }
        if (! $package->is_active) {
            throw new PlacementException("The {$package->name} package is not on offer.");
        }

        $sponsor = $member->sponsor;
        if ($sponsor === null) {
            throw new PlacementException("@{$member->username} has no sponsor; only the company account has none.");
        }

        return DB::transaction(function () use ($member, $package, $paymentKey, $sponsor, $keyedInBy) {
            $member = User::lockForUpdate()->findOrFail($member->id);
            if ($member->isActivated()) { // checked again under the lock: two payments at once
                throw new PlacementException("@{$member->username} is already a member with a paid package.");
            }

            $parent = $member->requested_parent_id ? User::find($member->requested_parent_id) : null;
            $this->tree->place($member, $sponsor, $parent, $member->requested_leg);

            $member->forceFill(['package_id' => $package->id, 'activated_at' => now()])->save();

            $change = PackageChange::create([
                'user_id' => $member->id,
                'kind' => PackageChange::JOIN,
                'from_package_id' => null,
                'to_package_id' => $package->id,
                'payment_key' => $paymentKey,
            ]);

            $this->climb->credit($member, $package->pv * 100, PvClimb::SOURCE_REGISTRATION, 'pv:join:'.$member->id, $change);

            // The package's packs, collected from a stockist with a pickup code (section 8).
            $order = $this->orders->registration($member, $package, $change);

            // The payment: half to the packs' manufacturer, half to the registration pool (section 7).
            $this->split->registration($package->price, Manufacturer::findOrFail($order->items()->value('manufacturer_id')), $paymentKey, $change);

            // The bonuses paid at once: direct, indirect and key-in (section 5).
            $this->bonuses->forJoining($change->setRelation('user', $member), $keyedInBy);

            return $change;
        });
    }

    /** Where a member would be placed right now, without placing them. @return array{parent: User, leg: string} */
    public function preview(User $member): array
    {
        $sponsor = $member->sponsor ?? throw new PlacementException('No sponsor.');
        $start = $member->requested_parent_id ?? $sponsor->id;
        [$parentId, $leg] = $this->tree->findSlot($start, $member->requested_leg);

        return ['parent' => User::findOrFail($parentId), 'leg' => $leg === TreePosition::LEFT ? 'left' : 'right'];
    }
}
