<?php

namespace App\Services\Compensation;

use App\Models\Compensation\LeadershipPayout;
use App\Models\Compensation\LeadershipRun;
use App\Models\Pools\HeldPayout;
use App\Models\User;
use App\Services\Ledger\Ledger;
use App\Services\Pools\PoolPayouts;
use App\Support\Money;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The leadership bonus (specification section 5.7), paid once a month for
 * the month just ended.
 *
 *  - A leader is a member with 480,000 PV on their repurchase PV log by the
 *    end of the month (Blueprint V3).
 *  - They earn 5% of what their team earned from the matching bonus that
 *    month, from the registration ledger's leadership line (Pool A). When
 *    the line and its contingency fund are short, it is held until they
 *    refill (section 7).
 *  - Who the "team" is, is an admin setting until the board decides
 *    (open decision 3): everyone below the leader in the binary tree, or
 *    everyone down their sponsor chain, to a set number of levels or with
 *    no limit. Each run records the rule it used.
 *  - Banned members, members whose package is not paid and the company
 *    account are not paid. A leader inside another leader's team still
 *    counts in that team: the plan does not say otherwise.
 *
 * A leader is paid once per month: running a month again pays nobody twice.
 */
class Leadership
{
    public function __construct(private readonly Ledger $ledger, private readonly PoolPayouts $payouts) {}

    /** The month that ended most recently (Lagos time), as YYYY-MM. */
    public static function lastClosedMonth(): string
    {
        return CarbonImmutable::now(config('wiifarm.business_timezone'))->startOfMonth()->subMonth()->format('Y-m');
    }

    /** The team rule in force, in words. */
    public static function teamRule(): string
    {
        $levels = (int) Settings::get('leadership.levels');
        $who = Settings::get('leadership.team') === 'sponsor' ? 'sponsor chain' : 'binary tree';
        $deep = $levels > 0 ? "{$levels} ".($who === 'sponsor chain' ? 'generations' : 'levels') : 'no limit';

        return "{$who}, {$deep}";
    }

    public function run(string $month): LeadershipRun
    {
        $run = LeadershipRun::firstOrCreate(['month' => $month], ['status' => LeadershipRun::RUNNING, 'team_rule' => self::teamRule()]);
        if ($run->status === LeadershipRun::DONE) {
            return $run;
        }

        $start = CarbonImmutable::createFromFormat('Y-m-d', $month.'-01', config('wiifarm.business_timezone'))->startOfDay();
        $end = $start->endOfMonth();
        $company = User::company();

        foreach ($this->qualified($end) as $userId => $pv) {
            $leader = User::find($userId);
            if ($leader === null || $leader->is($company) || ! $leader->isActivated() || $leader->isBanned()) {
                continue;
            }
            $this->payLeader($run, $leader, $pv, $start, $end);
        }

        $totals = $run->lines()->selectRaw('COUNT(*) as leaders, COALESCE(SUM(amount), 0) as amount')->first();
        $run->forceFill(['status' => LeadershipRun::DONE, 'finished_at' => now(), 'leaders' => (int) $totals->leaders, 'amount' => (int) $totals->amount])->save();

        return $run;
    }

    /**
     * Members whose repurchase PV reached the leadership level by the end of
     * the month: user id => PV in hundredths.
     *
     * @return array<int, int>
     */
    private function qualified(CarbonImmutable $end): array
    {
        return DB::table('ledger_entries')
            ->join('ledger_accounts', 'ledger_accounts.id', '=', 'ledger_entries.account_id')
            ->where('ledger_accounts.kind', 'pv.repurchase')
            ->where('ledger_entries.created_at', '<=', $end->utc())
            ->groupBy('ledger_accounts.user_id')
            ->havingRaw('SUM(ledger_entries.amount) >= ?', [config('compensation.leadership.qualifying_pv')])
            ->orderBy('ledger_accounts.user_id')
            ->selectRaw('ledger_accounts.user_id as user_id, SUM(ledger_entries.amount) as pv')
            ->pluck('pv', 'user_id')->map(fn ($v) => (int) $v)->all();
    }

    private function payLeader(LeadershipRun $run, User $leader, int $pv, CarbonImmutable $start, CarbonImmutable $end): void
    {
        DB::transaction(function () use ($run, $leader, $pv, $start, $end) {
            if (LeadershipPayout::where(['leadership_run_id' => $run->id, 'user_id' => $leader->id])->exists()) {
                return;
            }

            [$members, $matching] = $this->team($leader, $start->toDateString(), $end->toDateString());
            $amount = Money::share($matching, config('compensation.leadership.share'));

            $line = LeadershipPayout::create([
                'leadership_run_id' => $run->id, 'user_id' => $leader->id, 'repurchase_pv' => $pv,
                'team_members' => $members, 'team_matching' => $matching, 'amount' => $amount,
                'status' => $amount > 0 ? LeadershipPayout::PAID : LeadershipPayout::NONE,
            ]);
            if ($amount <= 0) {
                return;
            }

            $result = $this->payouts->pay(
                'A', 'leadership', $this->ledger->memberAccount($leader, 'bonus.leadership'), $amount, 'leadership',
                "leadership:{$run->month}:{$leader->id}", "Leadership bonus for {$run->month}: 5% of your team's matching", $line,
            );
            if ($result instanceof HeldPayout) {
                $line->forceFill(['status' => LeadershipPayout::HELD])->save();
            }
        });
    }

    /**
     * The leader's team under the rule in force: how many members, and what
     * they earned from matching on days in the month (kobo).
     *
     * @return array{0: int, 1: int}
     */
    private function team(User $leader, string $from, string $to): array
    {
        $levels = (int) Settings::get('leadership.levels');
        [$table, $child, $parent] = Settings::get('leadership.team') === 'sponsor'
            ? ['users', 'id', 'sponsor_id']
            : ['tree_positions', 'user_id', 'parent_id'];

        $row = DB::selectOne("
            WITH RECURSIVE down (member_id, depth) AS (
                SELECT {$child}, 1 FROM {$table} WHERE {$parent} = ? AND {$child} <> ?
                UNION ALL
                SELECT t.{$child}, down.depth + 1 FROM {$table} t JOIN down ON t.{$parent} = down.member_id
                WHERE (? = 0 OR down.depth < ?) AND down.depth < 100000 AND t.{$child} <> ?
            )
            SELECT
                (SELECT COUNT(DISTINCT member_id) FROM down) AS members,
                (SELECT COALESCE(SUM(m.amount), 0) FROM matches m JOIN match_runs r ON r.id = m.match_run_id
                    WHERE m.user_id IN (SELECT member_id FROM down) AND r.day BETWEEN ? AND ?) AS matching
        ", [$leader->id, $leader->id, $levels, $levels, $leader->id, $from, $to]);

        return [(int) $row->members, (int) $row->matching];
    }
}
