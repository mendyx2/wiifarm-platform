<?php

namespace App\Services\Network;

use App\Models\Package;
use App\Models\PackageChange;
use App\Models\User;
use App\Services\Ledger\Ledger;
use Illuminate\Support\Facades\DB;

/**
 * "Add Distributor" (specification section 2): a member registers someone
 * and pays the package from their own VISA wallet. In one step the account
 * is created, the price leaves the VISA wallet, and the new member is placed
 * and paid for, so either all of it happens or none of it. The member who
 * keyed them in earns the key-in bonus (5.3).
 */
class KeyIn
{
    public function __construct(private readonly Ledger $ledger, private readonly Registration $registration) {}

    /**
     * @param  array<string, mixed>  $details  the new person's checked details
     */
    public function register(User $by, array $details, User $sponsor, ?User $parent, Package $package, string $formId): PackageChange
    {
        $key = 'keyin:'.$by->id.':'.$formId;
        if ($done = PackageChange::where('payment_key', $key)->first()) {
            return $done; // the same form sent twice
        }
        if (! $by->isActivated() || $by->isBanned()) {
            throw new PlacementException('Only members with a paid package can add distributors.');
        }

        return DB::transaction(function () use ($by, $details, $sponsor, $parent, $package, $key) {
            $member = new User(collect($details)->only(['first_name', 'last_name', 'username', 'email', 'dial_code', 'mobile', 'state', 'city', 'address', 'password'])->all());
            $member->country = strtoupper((string) $details['country']);
            $member->sponsor_id = $sponsor->id;
            $member->requested_parent_id = $parent?->id;
            $member->requested_leg = $details['leg'] ?? null;
            $member->save();

            $this->ledger->transfer(
                $this->ledger->memberAccount($by, 'visa'), $this->ledger->systemAccount('external'), $package->price,
                'keyin.pay', $key, "Add Distributor: @{$member->username} on {$package->name}", $member,
            );

            return $this->registration->activate($member, $package, $key, keyedInBy: $by);
        });
    }
}
