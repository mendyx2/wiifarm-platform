<?php

namespace App\Services\Compensation;

use App\Models\Compensation\UnilevelPayout;
use App\Models\Compensation\UnilevelRun;
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
 * The unilevel bonus (specification section 5.10), paid once a month for
 * the month just ended.
 *
 * For every member who bought products that month, each of the 15
 * generations above them in the referral chain earns a share of the PV value
 * (PV × ₦600) of what they bought: 7%, 5%, 4%, 4%, 3%, 3%, 3%, 2%, 2%, 2%,
 * then 1% for generations 11 to 15 (40% in all).
 * A member is paid only on the generations their package unlocks (section 4)
 * and, while the admin switch is on, only if they made the monthly purchase (10.5 PV) of products
 * themselves that month (open decision 4). Banned members, members whose
 * package is not paid and the company account are not paid either.
 *
 * A share nobody can receive is not passed on: it stays with the cooperative,
 * in the repurchase ledger's (Pool C's) contingency fund, and the generation
 * still counts. Each member's shares for the month are paid together, from
 * Pool C's unilevel line; when it and the contingency fund are short, the
 * payout is held until they refill (section 7).
 */
class Unilevel
{
    public const GENERATIONS = 15;

    public function __construct(private readonly Ledger $ledger, private readonly PoolPayouts $payouts, private readonly MonthlyActivity $activity) {}

    /**
     * The latest month whose autoship can no longer be made: the month before
     * last until the first week of this month has passed (so a month's
     * autoship made in that week still counts for its unilevel).
     */
    public static function lastClosedMonth(): string
    {
        $last = Leadership::lastClosedMonth();

        return CarbonImmutable::now()->lessThan(MonthlyActivity::graceEnds($last))
            ? CarbonImmutable::createFromFormat('Y-m-d', $last.'-01')->subMonth()->format('Y-m')
            : $last;
    }

    public function run(string $month): UnilevelRun
    {
        $activity = (bool) Settings::get('unilevel.activity_required');
        $run = UnilevelRun::firstOrCreate(['month' => $month], ['status' => UnilevelRun::RUNNING, 'activity_required' => $activity]);
        if ($run->status === UnilevelRun::DONE) {
            return $run;
        }

        $bought = $this->boughtIn($month);
        [$earnings, $unpaid] = $this->shares($bought, $run->activity_required, $month);

        foreach ($earnings as $userId => $byGeneration) {
            $this->payMember($run, $userId, $byGeneration);
        }

        // What nobody could receive stays with the cooperative: what the line still holds moves to Pool C's contingency fund.
        $line = $this->ledger->balance($this->ledger->poolAccount('C', 'unilevel'));
        $move = min($unpaid, $line);
        if ($move > 0) {
            $this->payouts->toContingency('C', 'unilevel', $move, "unilevel:{$month}:unpaid", "Unilevel {$month}: shares nobody could receive");
        }

        $totals = $run->lines()->selectRaw('COUNT(*) as earners, COALESCE(SUM(amount), 0) as amount')->first();
        $run->forceFill([
            // What was actually moved: what is left in the line, never more than the line holds.
            'status' => UnilevelRun::DONE, 'finished_at' => now(), 'repurchase_pv' => array_sum($bought), 'unpaid' => max(0, $move),
            'earners' => (int) $totals->earners, 'amount' => (int) $totals->amount,
        ])->save();

        return $run;
    }

    /**
     * Product PV each member bought in the month (Lagos time): user id => hundredths.
     *
     * @return array<int, int>
     */
    public function boughtIn(string $month): array
    {
        $start = CarbonImmutable::createFromFormat('Y-m-d', $month.'-01', config('wiifarm.business_timezone'))->startOfDay();

        return DB::table('ledger_entries')
            ->join('ledger_accounts', 'ledger_accounts.id', '=', 'ledger_entries.account_id')
            ->where('ledger_accounts.kind', 'pv.repurchase')
            ->where('ledger_entries.amount', '>', 0)
            ->whereBetween('ledger_entries.created_at', [$start->utc(), $start->endOfMonth()->utc()])
            ->groupBy('ledger_accounts.user_id')
            ->orderBy('ledger_accounts.user_id')
            ->selectRaw('ledger_accounts.user_id as user_id, SUM(ledger_entries.amount) as pv')
            ->pluck('pv', 'user_id')->map(fn ($v) => (int) $v)->all();
    }

    /**
     * Each earner's shares by generation, and the total nobody could receive.
     *
     * @param  array<int, int>  $bought
     * @return array{0: array<int, array<int, int>>, 1: int}
     */
    private function shares(array $bought, bool $activityRequired, string $month): array
    {
        $rates = config('compensation.unilevel');
        $members = $this->chainMembers(array_keys($bought));
        $depth = Package::pluck('unilevel_generations', 'id')->map(fn ($v) => (int) $v)->all();
        $company = User::company()?->id;
        $active = fn (int $id) => ! $activityRequired || $this->activity->fulfilled(User::findOrFail($id), $month);

        $earnings = [];
        $unpaid = 0;
        foreach ($bought as $buyerId => $pv) {
            $value = $pv * Package::PV_VALUE_KOBO / 100;
            $upline = $members[$buyerId]->sponsor_id ?? null;
            foreach ($rates as $i => $rate) {
                $generation = $i + 1;
                $share = Money::share((int) $value, $rate);
                $who = $upline !== null ? ($members[$upline] ?? null) : null;

                $eligible = $who !== null && $who->id !== $company && $who->activated_at !== null && $who->status !== User::STATUS_BANNED
                    && ($depth[$who->package_id] ?? 0) >= $generation && $active($who->id);
                if ($eligible) {
                    $earnings[$who->id][$generation] = ($earnings[$who->id][$generation] ?? 0) + $share;
                } else {
                    $unpaid += $share;
                }
                $upline = $who?->sponsor_id;
            }
        }
        ksort($earnings);

        return [$earnings, $unpaid];
    }

    /**
     * The buyers and the 15 generations above them, loaded a generation at a time.
     *
     * @param  list<int>  $buyerIds
     * @return array<int, User>
     */
    private function chainMembers(array $buyerIds): array
    {
        $members = [];
        $next = $buyerIds;
        for ($g = 0; $g <= self::GENERATIONS && $next !== []; $g++) {
            $found = User::whereIn('id', $next)->get(['id', 'sponsor_id', 'package_id', 'activated_at', 'status']);
            foreach ($found as $u) {
                $members[$u->id] = $u;
            }
            $next = $found->pluck('sponsor_id')->filter()->unique()->reject(fn ($id) => isset($members[$id]))->values()->all();
        }

        return $members;
    }

    /** @param array<int, int> $byGeneration */
    private function payMember(UnilevelRun $run, int $userId, array $byGeneration): void
    {
        DB::transaction(function () use ($run, $userId, $byGeneration) {
            if (UnilevelPayout::where(['unilevel_run_id' => $run->id, 'user_id' => $userId])->exists()) {
                return;
            }
            $amount = array_sum($byGeneration);
            if ($amount <= 0) {
                return;
            }

            $line = UnilevelPayout::create(['unilevel_run_id' => $run->id, 'user_id' => $userId, 'by_generation' => $byGeneration, 'amount' => $amount, 'status' => UnilevelPayout::PAID]);
            $result = $this->payouts->pay(
                'C', 'unilevel', $this->ledger->memberAccount(User::findOrFail($userId), 'bonus.unilevel'), $amount, 'unilevel',
                "unilevel:{$run->month}:{$userId}", "Unilevel bonus for {$run->month}", $line,
            );
            if ($result instanceof HeldPayout) {
                $line->forceFill(['status' => UnilevelPayout::HELD])->save();
            }
        });
    }
}
