<?php

namespace App\Services\Compensation;

use App\Models\Admin;
use App\Models\Compensation\Award;
use App\Models\Package;
use App\Models\Pools\HeldPayout;
use App\Models\Pools\UnclaimedBonus;
use App\Models\User;
use App\Services\Admin\Audit;
use App\Services\Ledger\Ledger;
use App\Services\Messages\Messages;
use App\Services\Payments\PaymentException;
use App\Services\Pools\PoolPayouts;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Awards (specification 5.8, 5.9 and 5.11).
 *
 *  - Recognition ranks: a member earns each rank once, when the lifetime PV
 *    on their lesser leg (store PV included) reaches it, their package is
 *    high enough, and, from Gold up, they have produced the rank below on
 *    both their left and their right genealogy (that rank or higher).
 *  - Repurchase awards: on the cumulative repurchase PV of a member's 1st to
 *    10th generations; each new tier pays the difference from the last tier
 *    awarded.
 *
 * The nightly check finds who qualifies and records the award as waiting.
 * An admin approves or refuses it. A recognition award is paid in cash from
 * the Strategic Reserve's recognition line or, where a gift is offered and
 * the member chose it, its cash value is set aside to buy the gift; on
 * approval the Upline Celebrated Bonus pays the 1st, 2nd and 3rd upline 5%,
 * 2% and 1% of it. A repurchase award is paid from the repurchase ledger's
 * award line. Anything a line and its contingency fund cannot cover is held
 * until they refill (section 7).
 *
 * Banned members, members whose package is not paid and the company account
 * do not qualify, and are not paid an Upline Celebrated Bonus.
 */
class Awards
{
    public function __construct(private readonly Ledger $ledger, private readonly PoolPayouts $payouts, private readonly Audit $audit) {}

    /** The nightly check. Returns how many awards were recorded. */
    public function detect(): int
    {
        return $this->detectRanks() + $this->detectRepurchase();
    }

    /** The member chooses the cash or the gift while the award waits. */
    public function choose(User $member, Award $award, string $choice): void
    {
        if ($award->user_id !== $member->id || $award->status !== Award::PENDING) {
            throw new PaymentException('This award can no longer be changed.');
        }
        if ($choice === 'gift' && $award->gift === null) {
            throw new PaymentException('This award is cash only.');
        }
        $award->forceFill(['choice' => $choice === 'gift' ? 'gift' : 'cash'])->save();
    }

    public function approve(Admin $admin, Award $award): Award
    {
        return DB::transaction(function () use ($admin, $award) {
            $award = Award::with('user')->lockForUpdate()->findOrFail($award->id);
            if ($award->status !== Award::PENDING) {
                throw new PaymentException('This award has already been decided.');
            }

            $memo = "{$award->name} award for @{$award->user->username}";
            if ($award->kind === Award::REPURCHASE) {
                $result = $this->payouts->pay('C', 'repurchase_award', $this->ledger->memberAccount($award->user, 'bonus.repurchase_award'), $award->amount, 'award', 'award:'.$award->id, $memo, $award);
                $status = $result instanceof HeldPayout ? Award::HELD : Award::PAID;
            } else {
                $gift = $award->choice === 'gift' && $award->gift !== null;
                $to = $gift ? $this->ledger->cooperativeAccount('award_gifts') : $this->ledger->memberAccount($award->user, 'bonus.awards');
                $result = $this->payouts->pay('B', 'recognition', $to, $award->amount, 'award', 'award:'.$award->id, $gift ? "{$memo}: {$award->gift}" : $memo, $award);
                $status = $result instanceof HeldPayout ? Award::HELD : ($gift ? Award::GIFT : Award::PAID);
                $this->payUcb($award);
            }

            $award->forceFill(['status' => $status, 'decided_by_admin_id' => $admin->id, 'decided_at' => now()])->save();
            $this->audit->record($admin, 'awards.approve', $award, ['status' => Award::PENDING], ['status' => $status, 'choice' => $award->choice, 'amount' => $award->amount]);
            app(Messages::class)->send($award->user, 'award.approved', ['award' => $award->name, 'amount' => Messages::naira($award->amount)]);

            return $award;
        });
    }

    public function reject(Admin $admin, Award $award, string $reason): void
    {
        DB::transaction(function () use ($admin, $award, $reason) {
            $award = Award::lockForUpdate()->findOrFail($award->id);
            if ($award->status !== Award::PENDING) {
                throw new PaymentException('This award has already been decided.');
            }
            $award->forceFill(['status' => Award::REJECTED, 'reason' => $reason, 'decided_by_admin_id' => $admin->id, 'decided_at' => now()])->save();
            $this->audit->record($admin, 'awards.reject', $award, ['status' => Award::PENDING], ['status' => Award::REJECTED], $reason);
        });
    }

    /** An admin records that a gift was handed over. */
    public function delivered(Admin $admin, Award $award): void
    {
        if ($award->currentStatus() !== Award::GIFT) {
            throw new PaymentException('Only an approved gift whose value is set aside can be marked delivered.');
        }
        $award->forceFill(['status' => Award::DELIVERED, 'delivered_at' => now()])->save();
        $this->audit->record($admin, 'awards.delivered', $award, ['status' => Award::GIFT], ['status' => Award::DELIVERED]);
    }

    /** The cumulative repurchase PV of each member's 1st to 10th generations: user id => hundredths. @return array<int, int> */
    public function teamRepurchasePv(): array
    {
        $bought = DB::table('ledger_entries')
            ->join('ledger_accounts', 'ledger_accounts.id', '=', 'ledger_entries.account_id')
            ->where('ledger_accounts.kind', 'pv.repurchase')->where('ledger_entries.amount', '>', 0)
            ->groupBy('ledger_accounts.user_id')
            ->selectRaw('ledger_accounts.user_id as user_id, SUM(ledger_entries.amount) as pv')
            ->pluck('pv', 'user_id')->map(fn ($v) => (int) $v)->all();
        if ($bought === []) {
            return [];
        }

        $sponsors = User::pluck('sponsor_id', 'id')->all();
        $totals = [];
        foreach ($bought as $buyer => $pv) {
            $upline = $sponsors[$buyer] ?? null;
            for ($g = 1; $g <= (int) config('awards.repurchase_generations') && $upline !== null; $g++) {
                $totals[$upline] = ($totals[$upline] ?? 0) + $pv;
                $upline = $sponsors[$upline] ?? null;
            }
        }

        return $totals;
    }

    private function detectRanks(): int
    {
        $ranks = config('awards.ranks');
        $keys = array_keys($ranks);
        $packageSort = Package::pluck('sort', 'name')->all();
        $created = 0;

        foreach ($ranks as $code => $rank) {
            $min = $rank['pv'] * 100;
            $candidates = DB::table('ledger_entries')
                ->join('ledger_accounts', 'ledger_accounts.id', '=', 'ledger_entries.account_id')
                ->whereIn('ledger_accounts.kind', ['pv.L.lifetime', 'pv.R.lifetime'])
                ->groupBy('ledger_accounts.user_id', 'ledger_accounts.kind')
                ->havingRaw('SUM(ledger_entries.amount) >= ?', [$min])
                ->selectRaw('ledger_accounts.user_id as user_id, SUM(ledger_entries.amount) as pv')
                ->get()->groupBy('user_id')->filter(fn ($legs) => $legs->count() === 2);

            foreach ($candidates as $userId => $legs) {
                $member = User::with('package')->find($userId);
                if (! $this->eligible($member) || ($packageSort[$member->package->name] ?? 0) < ($packageSort[$rank['package']] ?? PHP_INT_MAX)) {
                    continue;
                }
                if (Award::where(['user_id' => $member->id, 'kind' => Award::RANK, 'code' => $code])->exists()) {
                    continue;
                }
                if ($rank['needs'] !== null) {
                    $atLeast = array_slice($keys, array_search($rank['needs'], $keys, true));
                    if (! $this->producedOnBothLegs($member, $atLeast)) {
                        continue;
                    }
                }

                Award::create([
                    'user_id' => $member->id, 'kind' => Award::RANK, 'code' => $code, 'name' => $rank['name'], 'amount' => $rank['cash'],
                    'gift' => $rank['gift'], 'measure' => intdiv((int) $legs->min('pv'), 100), 'status' => Award::PENDING,
                ]);
                $created++;
            }
        }

        return $created;
    }

    private function detectRepurchase(): int
    {
        $tiers = config('awards.repurchase_tiers');
        ksort($tiers);
        $created = 0;

        foreach ($this->teamRepurchasePv() as $userId => $pv) {
            $reached = null;
            foreach ($tiers as $tierPv => $cumulative) {
                if ($pv >= $tierPv * 100) {
                    $reached = $tierPv;
                }
            }
            if ($reached === null) {
                continue;
            }
            $member = User::find($userId);
            if (! $this->eligible($member)) {
                continue;
            }

            $last = Award::where(['user_id' => $userId, 'kind' => Award::REPURCHASE])->where('status', '!=', Award::REJECTED)
                ->get()->max(fn (Award $a) => (int) $a->code) ?? 0;
            if ($reached <= $last) {
                continue;
            }

            Award::create([
                'user_id' => $userId, 'kind' => Award::REPURCHASE, 'code' => (string) $reached, 'name' => number_format($reached).' PV repurchase',
                'amount' => $tiers[$reached] - ($last > 0 ? $tiers[$last] : 0), 'gift' => null, 'measure' => intdiv($pv, 100), 'status' => Award::PENDING,
            ]);
            $created++;
        }

        return $created;
    }

    /**
     * Has the member produced one of these ranks somewhere on their left, and
     * somewhere on their right (an award not refused)?
     *
     * @param  list<string>  $codes
     */
    private function producedOnBothLegs(User $member, array $codes): bool
    {
        foreach (['L', 'R'] as $leg) {
            $placeholders = implode(',', array_fill(0, count($codes), '?'));
            $found = DB::selectOne("
                WITH RECURSIVE down (member_id, depth) AS (
                    SELECT user_id, 1 FROM tree_positions WHERE parent_id = ? AND leg = ?
                    UNION ALL
                    SELECT t.user_id, down.depth + 1 FROM tree_positions t JOIN down ON t.parent_id = down.member_id WHERE down.depth < 100000
                )
                SELECT COUNT(*) AS n FROM awards
                WHERE kind = 'rank' AND status <> 'rejected' AND code IN ({$placeholders}) AND user_id IN (SELECT member_id FROM down)
            ", array_merge([$member->id, $leg], $codes));
            if ((int) $found->n === 0) {
                return false;
            }
        }

        return true;
    }

    private function payUcb(Award $award): void
    {
        $upline = $award->user->sponsor;
        foreach (config('awards.ucb') as $i => $rate) {
            $generation = $i + 1;
            $share = Money::share($award->amount, $rate);
            $key = "ucb:{$award->id}:g{$generation}";
            $memo = "Upline Celebrated Bonus, generation {$generation}: {$award->name} for @{$award->user->username}";
            if ($this->eligible($upline)) {
                $this->payouts->pay('B', 'ucb', $this->ledger->memberAccount($upline, 'bonus.acb'), $share, 'ucb', $key, $memo, $award);
            } else {
                $this->payouts->toUnclaimed('B', 'ucb', $share, $key, [
                    'bonus' => 'ucb', 'generation' => $generation, 'reason' => UnclaimedBonus::reasonFor($upline) ?? 'no_upline',
                    'beneficiary' => $upline, 'source' => $award->user,
                ], $memo.': nobody to receive it', $award);
            }
            $upline = $upline?->sponsor;
        }
    }

    private function eligible(?User $member): bool
    {
        return $member !== null && $member->isActivated() && ! $member->isBanned() && ! $member->is(User::company());
    }
}
