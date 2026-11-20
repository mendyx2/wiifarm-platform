<?php

namespace App\Services\Compensation;

use App\Models\Pools\UnclaimedBonus;
use App\Models\User;
use App\Services\Ledger\Ledger;
use App\Services\Messages\Notices;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The autoship hold on matching (specification 5.6; the owner, after
 * speaking with a leader, 19 and 20 November 2026).
 *
 * Until a member makes the month's autoship (10.5 PV of products), 20% of
 * each matching bonus they earn that month is held in their autoship wallet.
 *
 *  - Once they make it, the month's held amount is released to their
 *    matching bonus wallet (checked when they buy, and every night).
 *  - The month's autoship can still be made in the first week of the next
 *    month. A month whose first week has passed without it is "missed".
 *  - A missed month's hold stays held. It is released when the member makes
 *    a later month's autoship.
 *  - When a member misses 3 months in a row, the hold of each of those
 *    months (and of any further month missed in the same run) is flushed to
 *    the registration ledger's (Pool A's) Unclaimed Bonus Reserve, where the
 *    matching money came from.
 *
 * The month a member joins counts like any other. Each month's held amount
 * is known from the matching payouts themselves (their key names the day),
 * so a share paid late, after its pool refilled, is settled the same way.
 * Nothing is released or flushed twice.
 */
class Autoship
{
    /** Missed months in a row after which their holds are flushed. */
    public const FLUSH_AFTER = 3;

    public function __construct(private readonly Ledger $ledger, private readonly MonthlyActivity $activity) {}

    /** Settles every member with something held. Returns how many settlements were made. */
    public function settleAll(): int
    {
        $ids = DB::table('ledger_accounts')->where('kind', 'autoship')->whereNotNull('user_id')->pluck('user_id');

        return $ids->sum(fn ($id) => ($member = User::find($id)) ? $this->settle($member) : 0);
    }

    /** Releases or flushes what a member has held, month by month, where the month is decided. */
    public function settle(User $member): int
    {
        $held = array_filter($this->unsettled($member), fn ($amount) => $amount > 0);
        if ($held === []) {
            return 0;
        }
        $status = $this->statuses($member, array_key_first($held));

        $made = 0;
        foreach ($held as $month => $amount) {
            $outcome = match (true) {
                ($status[$month] ?? null) === 'made' => 'released',
                ($status[$month] ?? null) !== 'missed' => null,                       // still open: wait
                $this->runLength($status, $month) >= self::FLUSH_AFTER => 'flushed',
                $this->madeAfter($status, $month) => 'released',                     // autoship made again
                default => null,                                                     // missed, but not yet 3 in a row
            };
            if ($outcome !== null) {
                $made += $this->move($member, $month, $amount, $outcome);
            }
        }

        return $made;
    }

    /**
     * The missed months in a row up to the last month whose first week has
     * passed: 0 when that month was made. Shown to the member as a warning.
     */
    public function missedInARow(User $member): int
    {
        $status = array_filter($this->statuses($member), fn ($s) => $s !== 'open');
        $run = 0;
        foreach (array_reverse($status) as $s) {
            if ($s !== 'missed') {
                break;
            }
            $run++;
        }

        return $run;
    }

    /**
     * Each month from the month the member joined (or $from, if earlier) to
     * now: made, missed (its first week has passed without it) or open.
     *
     * @return array<string, 'made'|'missed'|'open'>
     */
    public function statuses(User $member, ?string $from = null): array
    {
        $zone = config('wiifarm.business_timezone');
        $now = CarbonImmutable::now();
        $joined = $member->activated_at ? MonthlyActivity::monthOf(CarbonImmutable::parse($member->activated_at)) : null;
        $first = collect([$joined, $from])->filter()->min();
        if ($first === null) {
            return [];
        }

        $credits = $this->activity->credits($member);
        $required = MonthlyActivity::requiredPv();
        $status = [];
        $last = MonthlyActivity::monthOf($now);
        for ($month = CarbonImmutable::createFromFormat('Y-m-d', $first.'-01', $zone)->startOfDay(); $month->format('Y-m') <= $last; $month = $month->addMonth()) {
            $key = $month->format('Y-m');
            $status[$key] = match (true) {
                ($credits[$key] ?? 0) >= $required => 'made',
                $now->greaterThanOrEqualTo(MonthlyActivity::graceEnds($key)) => 'missed',
                default => 'open',
            };
        }

        return $status;
    }

    /** How many missed months in a row the run that holds $month has. */
    private function runLength(array $status, string $month): int
    {
        $months = array_keys($status);
        $at = array_search($month, $months, true);
        $length = 1;
        for ($i = $at - 1; $i >= 0 && $status[$months[$i]] === 'missed'; $i--) {
            $length++;
        }
        for ($i = $at + 1; $i < count($months) && $status[$months[$i]] === 'missed'; $i++) {
            $length++;
        }

        return $length;
    }

    /** Has the member made the autoship of any month after $month? */
    private function madeAfter(array $status, string $month): bool
    {
        foreach ($status as $m => $s) {
            if ($m > $month && $s === 'made') {
                return true;
            }
        }

        return false;
    }

    /**
     * What is held for each month and not yet released or kept: month => kobo.
     *
     * @return array<string, int>
     */
    public function unsettled(User $member): array
    {
        $account = $this->ledger->memberAccount($member, 'autoship');
        $held = [];
        $rows = DB::table('ledger_entries')
            ->join('ledger_transactions', 'ledger_transactions.id', '=', 'ledger_entries.transaction_id')
            ->where('ledger_entries.account_id', $account->id)->where('ledger_entries.amount', '>', 0)
            ->where('ledger_transactions.idempotency_key', 'like', 'payout:matching:%:autoship')
            ->get(['ledger_entries.amount', 'ledger_transactions.idempotency_key']);
        foreach ($rows as $row) {
            $month = substr(explode(':', $row->idempotency_key)[2], 0, 7);   // payout:matching:YYYY-MM-DD:user:autoship
            $held[$month] = ($held[$month] ?? 0) + (int) $row->amount;
        }

        $settled = DB::table('autoship_settlements')->where('user_id', $member->id)
            ->groupBy('month')->selectRaw('month, SUM(amount) as amount')->pluck('amount', 'month');
        foreach ($held as $month => $amount) {
            $held[$month] = $amount - (int) ($settled[$month] ?? 0);
        }
        ksort($held);

        return $held;
    }

    private function move(User $member, string $month, int $amount, string $outcome): int
    {
        return DB::transaction(function () use ($member, $month, $amount, $outcome) {
            $from = $this->ledger->memberAccount($member, 'autoship');
            $amount = min($amount, $this->ledger->balance($from));
            if ($amount <= 0) {
                return 0;
            }
            $before = (int) DB::table('autoship_settlements')->where(['user_id' => $member->id, 'month' => $month])->sum('amount');
            $to = $outcome === 'released'
                ? $this->ledger->memberAccount($member, 'bonus.matching')
                : $this->ledger->poolAccount('A', Ledger::UNCLAIMED);
            $memo = $outcome === 'released'
                ? "Autoship made: your matching held for {$month} is released"
                : "Autoship not made for {$month} and the months around it, ".self::FLUSH_AFTER.' or more in a row: held matching moved to the Unclaimed Bonus Reserve';

            $tx = $this->ledger->transfer($from, $to, $amount, 'autoship.'.$outcome, "autoship:{$member->id}:{$month}:".($before + $amount), $memo, $member);
            if (! $tx->wasAlreadyPosted) {
                if ($outcome === 'released') {
                    Notices::bonus($to, $amount, $memo);
                }
                DB::table('autoship_settlements')->insert([
                    'user_id' => $member->id, 'month' => $month, 'amount' => $amount, 'outcome' => $outcome,
                    'transaction_id' => $tx->id, 'created_at' => now(), 'updated_at' => now(),
                ]);
                if ($outcome === 'flushed') {
                    UnclaimedBonus::create([
                        'pool' => 'A', 'line' => 'matching', 'bonus' => 'autoship', 'generation' => null, 'reason' => 'autoship_missed',
                        'beneficiary_user_id' => $member->id, 'source_user_id' => $member->id,
                        'subject_type' => 'User', 'subject_id' => $member->id,
                        'amount' => $amount, 'transaction_id' => $tx->id, 'memo' => "Matching held for {$month}",
                    ]);
                }
            }

            return 1;
        });
    }
}
