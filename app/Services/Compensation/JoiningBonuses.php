<?php

namespace App\Services\Compensation;

use App\Models\Package;
use App\Models\PackageChange;
use App\Models\Pools\UnclaimedBonus;
use App\Models\User;
use App\Services\Ledger\Ledger;
use App\Services\Pools\PoolPayouts;
use App\Support\Money;

/**
 * The bonuses paid the moment a package is paid (specification sections 5.1
 * to 5.4), each from its own line, once only:
 *
 *  - joining: the direct bonus to the sponsor (25% of the package's PV
 *    value), the indirect bonus to the 10 generations above the sponsor (3%,
 *    2%, then 1% each), and the key-in bonus (2%) to the member who keyed the
 *    new member in;
 *  - upgrade: on the PV difference, 25% to the sponsor, 2% to generation 2
 *    and 1% each to generations 3 to 10.
 *
 * A share with nobody to receive it (a missing generation, a banned or unpaid
 * member, the company account, a self sign-up's key-in) is not passed on: it
 * goes to the pool's Unclaimed Bonus Reserve, with where it came from, and
 * the generation still counts.
 */
class JoiningBonuses
{
    public function __construct(private readonly Ledger $ledger, private readonly PoolPayouts $payouts) {}

    public function forJoining(PackageChange $change, ?User $keyedInBy = null): void
    {
        $member = $change->user;
        $package = $change->toPackage;
        $value = $package->pv * Package::PV_VALUE_KOBO;
        $from = "@{$member->username} joining with {$package->name}";

        // Direct: the sponsor.
        $sponsor = $member->sponsor;
        $this->share('A', 'direct', $sponsor, 'bonus.direct', Money::share($value, config('compensation.direct')), "direct:{$change->id}", "Direct bonus: {$from}", $change, null);

        // Indirect: the 10 generations above the sponsor.
        $upline = $sponsor?->sponsor;
        foreach (config('compensation.indirect') as $i => $rate) {
            $generation = $i + 1;
            $this->share('A', 'indirect', $upline, 'bonus.indirect', Money::share($value, $rate), "indirect:{$change->id}:g{$generation}", "Indirect bonus, generation {$generation}: {$from}", $change, $generation);
            $upline = $upline?->sponsor;
        }

        // Key-in: the member who keyed the new member in (none on a self sign-up).
        $this->share('A', 'keyin', $keyedInBy, 'bonus.keyin', Money::share($value, config('compensation.keyin')), "keyin:{$change->id}", "Key-in bonus: {$from}", $change, null);
    }

    public function forUpgrade(PackageChange $change): void
    {
        $member = $change->user;
        $value = ($change->toPackage->pv - $change->fromPackage->pv) * Package::PV_VALUE_KOBO;
        $from = "@{$member->username} upgrading to {$change->toPackage->name}";

        $upline = $member->sponsor;
        foreach (config('compensation.upgrade') as $i => $rate) {
            $generation = $i + 1;
            $this->share('E', 'upgrade', $upline, 'bonus.upgrade', Money::share($value, $rate), "upgrade:{$change->id}:g{$generation}", "Upgrade bonus, generation {$generation}: {$from}", $change, $generation);
            $upline = $upline?->sponsor;
        }
    }

    /** Pays one share, or puts it in the pool's Unclaimed Bonus Reserve, with where it came from, when nobody can receive it. */
    private function share(string $pool, string $line, ?User $to, string $wallet, int $amount, string $key, string $memo, PackageChange $change, ?int $generation): void
    {
        if ($amount <= 0) {
            return;
        }

        if ($this->eligible($to)) {
            $this->payouts->pay($pool, $line, $this->ledger->memberAccount($to, $wallet), $amount, $line, 'bonus:'.$key, $memo, $change);
        } else {
            // A key-in share with nobody behind it is a self sign-up (or an admin's registration).
            $reason = $line === 'keyin' && $to === null ? 'no_key_in' : UnclaimedBonus::reasonFor($to);
            $this->payouts->toUnclaimed($pool, $line, $amount, 'bonus:'.$key, [
                'bonus' => $line, 'generation' => $generation, 'reason' => $reason, 'beneficiary' => $to, 'source' => $change->user,
            ], $memo.': nobody to receive it', $change);
        }
    }

    /** Bonuses go only to members with a paid package who are not banned; the company account earns none. */
    private function eligible(?User $member): bool
    {
        return $member !== null && $member->isActivated() && ! $member->isBanned() && ! $member->is(User::company());
    }
}
