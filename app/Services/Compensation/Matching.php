<?php

namespace App\Services\Compensation;

use App\Models\Compensation\MatchRun;
use App\Models\Compensation\MemberMatch;
use App\Models\Ledger\Entry;
use App\Models\Package;
use App\Models\Pools\HeldPayout;
use App\Models\User;
use App\Services\Ledger\Ledger;
use App\Services\Pools\PoolPayouts;
use App\Support\Money;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The daily matching bonus (specification section 5.5).
 *
 * Once a day, every member with at least 50 pairing PV on both legs is paid
 * one match for each 50 PV on their smaller leg, up to their package's daily
 * cap. Each match takes 50 PV from each leg; what is left carries forward.
 * Matches are paid at the package's "paid per match", from the registration
 * ledger's matching line (Pool A); when the line and its contingency fund are
 * short, the payout is held until they refill (section 7).
 *
 * Banned members, members whose package is not paid and the company account
 * are not paid, and their pairing PV is left as it is. Until a member buys
 * the monthly purchase (10.5 PV), 20% of the bonus goes to their autoship
 * wallet (5.6), while the admin switch for it is on.
 *
 * A member is matched once per day: running a day again pays nobody twice.
 */
class Matching
{
    public function __construct(private readonly Ledger $ledger, private readonly PoolPayouts $payouts) {}

    /** The business day that ended most recently (yesterday, Lagos time). */
    public static function lastClosedDay(): CarbonImmutable
    {
        return CarbonImmutable::now(config('wiifarm.business_timezone'))->subDay()->startOfDay();
    }

    public function run(CarbonImmutable $day): MatchRun
    {
        $run = MatchRun::firstOrCreate(['day' => $day->toDateString()], ['status' => MatchRun::RUNNING, 'started_at' => now()]);
        if ($run->status === MatchRun::DONE) {
            return $run;
        }

        $company = User::company();
        foreach ($this->candidates() as $userId) {
            if ($userId === $company?->id) {
                continue;
            }
            $this->matchMember($run, User::with('package')->findOrFail($userId), $day);
        }

        $totals = $run->lines()->selectRaw('COUNT(*) as members, COALESCE(SUM(matches), 0) as matches, COALESCE(SUM(amount), 0) as amount')->first();
        $run->forceFill([
            'status' => MatchRun::DONE, 'finished_at' => now(),
            'members' => (int) $totals->members, 'matches' => (int) $totals->matches, 'amount' => (int) $totals->amount,
        ]);
        $run->forceFill(['held' => $run->stillHeld()])->save();

        return $run;
    }

    /**
     * Members with at least one match's worth of pairing PV on both legs.
     *
     * @return list<int>
     */
    private function candidates(): array
    {
        $min = Package::MATCH_PV * 100;

        return Entry::query()
            ->join('ledger_accounts', 'ledger_accounts.id', '=', 'ledger_entries.account_id')
            ->whereIn('ledger_accounts.kind', ['pv.L.pairing', 'pv.R.pairing'])
            ->groupBy('ledger_accounts.user_id', 'ledger_accounts.kind')
            ->havingRaw('SUM(ledger_entries.amount) >= ?', [$min])
            ->selectRaw('ledger_accounts.user_id as user_id, ledger_accounts.kind as kind')
            ->get()
            ->groupBy('user_id')
            ->filter(fn ($legs) => $legs->count() === 2)
            ->keys()->map(fn ($id) => (int) $id)->sort()->values()->all();
    }

    private function matchMember(MatchRun $run, User $member, CarbonImmutable $day): void
    {
        if (! $member->isActivated() || $member->isBanned() || $member->package === null) {
            return; // not paid; the PV stays where it is
        }

        DB::transaction(function () use ($run, $member, $day) {
            if (MemberMatch::where(['match_run_id' => $run->id, 'user_id' => $member->id])->exists()) {
                return;
            }

            $leftAccount = $this->ledger->pvAccount($member, 'L', 'pairing');
            $rightAccount = $this->ledger->pvAccount($member, 'R', 'pairing');
            $left = $this->ledger->balance($leftAccount);
            $right = $this->ledger->balance($rightAccount);

            $package = $member->package;
            $possible = intdiv(min($left, $right), Package::MATCH_PV * 100);
            $matches = min($possible, $package->daily_cap_matches);
            if ($matches <= 0) {
                return;
            }

            $date = $day->toDateString();
            $used = $matches * Package::MATCH_PV * 100;
            $amount = $matches * $package->perMatch();
            $autoship = $this->autoshipShare($member, $day, $amount);

            $line = MemberMatch::create([
                'match_run_id' => $run->id, 'user_id' => $member->id, 'package_id' => $package->id,
                'left_pv' => $left, 'right_pv' => $right, 'matches' => $matches, 'capped' => $possible > $matches,
                'amount' => $amount, 'autoship' => $autoship, 'status' => MemberMatch::PAID,
            ]);

            // The PV the matches use, from both legs.
            $this->ledger->post('matching.pv', "matching:pv:{$date}:{$member->id}", [
                [$leftAccount, -$used],
                [$rightAccount, -$used],
                [$this->ledger->systemAccount('pv.matched'), 2 * $used],
            ], "{$matches} match(es) on {$date}", $line);

            $memo = "Matching bonus for {$date}: {$matches} match(es) on {$package->name}";
            $results = [
                $this->payouts->pay('A', 'matching', $this->ledger->memberAccount($member, 'bonus.matching'), $amount - $autoship, 'matching', "matching:{$date}:{$member->id}", $memo, $line),
            ];
            if ($autoship > 0) {
                $results[] = $this->payouts->pay('A', 'matching', $this->ledger->memberAccount($member, 'autoship'), $autoship, 'matching', "matching:{$date}:{$member->id}:autoship", $memo.' (autoship share)', $line);
            }

            if (collect($results)->contains(fn ($r) => $r instanceof HeldPayout)) {
                $line->forceFill(['status' => MemberMatch::HELD])->save();
            }
        });
    }

    /** The share of a matching bonus held for autoship: none once the member has made this month's purchase. */
    private function autoshipShare(User $member, CarbonImmutable $day, int $amount): int
    {
        if (! Settings::get('matching.autoship_hold')) {
            return 0;
        }

        $from = $day->startOfMonth()->utc();
        $to = $day->endOfMonth()->utc();
        $bought = (int) Entry::where('account_id', $this->ledger->repurchasePvAccount($member)->id)
            ->where('amount', '>', 0)
            ->whereBetween('created_at', [$from, $to])
            ->sum('amount');

        if ($bought >= MonthlyActivity::requiredPv()) {
            return 0;
        }

        return Money::share($amount, config('compensation.matching.autoship_share'));
    }
}
