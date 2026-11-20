<?php

namespace App\Services\Compensation;

use App\Models\User;
use App\Services\Ledger\Ledger;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The monthly purchase ("autoship"): 10.5 PV (₦10,500) of products a
 * calendar month, an admin setting (Revised Baseline Rules; the owner, after
 * speaking with a leader, 19 November 2026). It releases the matching held
 * until autoship (the Autoship service).
 *
 * A month's autoship can still be made in the first week of the next month
 * ("the system calls for the previous month's autoship in the first week").
 * A purchase in those first days goes first to the previous month, if it was
 * short and the member already belonged that month, and only the rest counts
 * for the new month. So one purchase never covers two months.
 *
 * Months are calendar months in Lagos time.
 */
class MonthlyActivity
{
    /** The days at the start of a month in which the previous month's autoship can still be made. */
    public const GRACE_DAYS = 7;

    public function __construct(private readonly Ledger $ledger) {}

    /** The PV a member must buy in a calendar month, in hundredths (10.5 PV = 1050). */
    public static function requiredPv(): int
    {
        return (int) round(((float) Settings::get('activity.monthly_pv')) * 100);
    }

    /** The month a moment falls in, as YYYY-MM (Lagos time). */
    public static function monthOf(CarbonImmutable $moment): string
    {
        return $moment->setTimezone(config('wiifarm.business_timezone'))->format('Y-m');
    }

    /** When the first week after a month ends: from then on, that month's autoship can no longer be made. */
    public static function graceEnds(string $month): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d', $month.'-01', config('wiifarm.business_timezone'))
            ->startOfDay()->addMonth()->addDays(self::GRACE_DAYS);
    }

    /** Has the member made the month's autoship (so far)? */
    public function fulfilled(User $member, string $month): bool
    {
        return $this->creditFor($member, $month) >= self::requiredPv();
    }

    /**
     * The PV that counts for a month: the month's own purchases, less what
     * went to the month before, plus what the next month's first week gave it.
     */
    public function creditFor(User $member, string $month): int
    {
        [$own, $toPrevious] = $this->allocate($member);

        $next = CarbonImmutable::createFromFormat('Y-m-d', $month.'-01')->addMonth()->format('Y-m');

        return ($own[$month] ?? 0) + ($toPrevious[$next] ?? 0);
    }

    /**
     * The PV that counts for each month with any, worked out in one pass.
     *
     * @return array<string, int>
     */
    public function credits(User $member): array
    {
        [$own, $toPrevious] = $this->allocate($member);
        $credits = [];
        foreach ($own as $month => $pv) {
            $next = CarbonImmutable::createFromFormat('Y-m-d', $month.'-01')->addMonth()->format('Y-m');
            $credits[$month] = $pv + ($toPrevious[$next] ?? 0);
        }

        return $credits;
    }

    /**
     * Each month's own credit and what its first week gave the month before.
     *
     * @return array{0: array<string, int>, 1: array<string, int>}
     */
    private function allocate(User $member): array
    {
        $zone = config('wiifarm.business_timezone');
        $bought = [];
        $early = [];
        $rows = DB::table('ledger_entries')->where('account_id', $this->ledger->repurchasePvAccount($member)->id)
            ->where('amount', '>', 0)->orderBy('id')->get(['amount', 'created_at']);
        foreach ($rows as $row) {
            $at = CarbonImmutable::parse($row->created_at, 'UTC')->setTimezone($zone);
            $month = $at->format('Y-m');
            $bought[$month] = ($bought[$month] ?? 0) + (int) $row->amount;
            if ($at->day <= self::GRACE_DAYS) {
                $early[$month] = ($early[$month] ?? 0) + (int) $row->amount;
            }
        }
        if ($bought === []) {
            return [[], []];
        }

        $required = self::requiredPv();
        // A first-week purchase only goes back to a month the member already belonged to.
        $joined = $member->activated_at ? CarbonImmutable::parse($member->activated_at)->setTimezone($zone)->format('Y-m') : null;
        $own = [];
        $toPrevious = [];
        // From the month before the first purchase, so a first purchase in the first week can still cover that month.
        $month = CarbonImmutable::createFromFormat('Y-m-d', array_key_first($bought).'-01', $zone)->startOfMonth()->subMonth();
        $last = CarbonImmutable::now($zone)->startOfMonth();
        for ($previous = null; $month->lessThanOrEqualTo($last); $month = $month->addMonth()) {
            $key = $month->format('Y-m');
            $shortfall = $previous === null || $joined === null || $previous < $joined ? 0 : max(0, $required - $own[$previous]);
            $toPrevious[$key] = min($early[$key] ?? 0, $shortfall);
            $own[$key] = ($bought[$key] ?? 0) - $toPrevious[$key];
            $previous = $key;
        }

        return [$own, $toPrevious];
    }
}
