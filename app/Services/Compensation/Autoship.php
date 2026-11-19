<?php

namespace App\Services\Compensation;

use App\Models\User;
use App\Services\Ledger\Ledger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The autoship hold on matching (specification 5.6; the owner, after
 * speaking with a leader, 19 November 2026).
 *
 * Until a member makes the month's autoship (10.5 PV of products), 20% of
 * each matching bonus they earn that month is held in their autoship wallet.
 *
 *  - Once they make it, the month's held amount is released to their
 *    matching bonus wallet (checked when they buy, and every night).
 *  - The month's autoship can still be made in the first week of the next
 *    month. If that week ends without it, the cooperative keeps the held
 *    amount, in the registration ledger's (Pool A's) contingency fund, where
 *    the matching money came from.
 *
 * Each month's held amount is known from the matching payouts themselves
 * (their key names the day), so a share paid late, after its pool refilled,
 * is settled the same way. Nothing is released or kept twice.
 */
class Autoship
{
    public function __construct(private readonly Ledger $ledger, private readonly MonthlyActivity $activity) {}

    /** Settles every member with something held. Returns how many settlements were made. */
    public function settleAll(): int
    {
        $ids = DB::table('ledger_accounts')->where('kind', 'autoship')->whereNotNull('user_id')->pluck('user_id');

        return $ids->sum(fn ($id) => ($member = User::find($id)) ? $this->settle($member) : 0);
    }

    /** Releases or keeps what a member has held, month by month, where the month is decided. */
    public function settle(User $member): int
    {
        $made = 0;
        foreach ($this->unsettled($member) as $month => $amount) {
            if ($amount <= 0) {
                continue;
            }
            if ($this->activity->fulfilled($member, $month)) {
                $made += $this->move($member, $month, $amount, 'released');
            } elseif (CarbonImmutable::now()->greaterThanOrEqualTo(MonthlyActivity::graceEnds($month))) {
                $made += $this->move($member, $month, $amount, 'kept');
            }
        }

        return $made;
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
                : $this->ledger->poolAccount('A', Ledger::CONTINGENCY);
            $memo = $outcome === 'released'
                ? "Autoship for {$month} made: your held matching is released"
                : "Autoship for {$month} not made by the end of the next month's first week: kept by the cooperative";

            $tx = $this->ledger->transfer($from, $to, $amount, 'autoship.'.$outcome, "autoship:{$member->id}:{$month}:".($before + $amount), $memo, $member);
            if (! $tx->wasAlreadyPosted) {
                DB::table('autoship_settlements')->insert([
                    'user_id' => $member->id, 'month' => $month, 'amount' => $amount, 'outcome' => $outcome,
                    'transaction_id' => $tx->id, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            return 1;
        });
    }
}
