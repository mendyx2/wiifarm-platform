<?php

namespace App\Console\Commands;

use App\Models\Compensation\MatchRun;
use App\Services\Compensation\Matching;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * The nightly matching run (specification sections 5.5 and 12). With no date
 * it closes yesterday (Lagos time), and first any earlier day the run missed
 * since the last one, so nobody loses a day's cap. Running a day again pays
 * nobody twice.
 *
 *     php artisan wiifarm:match
 *     php artisan wiifarm:match --day=2026-11-20
 */
class RunMatching extends Command
{
    protected $signature = 'wiifarm:match {--day= : The day to close, YYYY-MM-DD (default: yesterday)}';

    protected $description = 'Pay the daily binary matching bonus';

    public function handle(Matching $matching): int
    {
        $zone = config('wiifarm.business_timezone');
        $last = Matching::lastClosedDay();

        if ($this->option('day')) {
            $day = CarbonImmutable::createFromFormat('Y-m-d', (string) $this->option('day'), $zone)->startOfDay();
            if ($day->greaterThan($last)) {
                $this->error('That day has not ended yet.');

                return self::FAILURE;
            }
            $days = [$day];
        } else {
            $latest = MatchRun::where('status', MatchRun::DONE)->max('day');
            $start = $latest ? CarbonImmutable::parse($latest, $zone)->addDay()->startOfDay() : $last;
            $days = [];
            for ($d = $start; $d->lessThanOrEqualTo($last); $d = $d->addDay()) {
                $days[] = $d;
            }
        }

        foreach ($days as $day) {
            $run = $matching->run($day);
            $this->info(sprintf('%s: %d member(s), %d match(es), ₦%s earned, ₦%s held.', $run->day, $run->members, $run->matches, number_format($run->amount / 100, 2), number_format($run->held / 100, 2)));
        }
        if ($days === []) {
            $this->info('Nothing to run: yesterday is already done.');
        }

        return self::SUCCESS;
    }
}
