<?php

namespace App\Console\Commands;

use App\Models\Compensation\UnilevelRun;
use App\Services\Compensation\Unilevel;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * The monthly unilevel bonus (specification sections 5.10 and 12). With no
 * month it pays the month just ended, and first any earlier month missed
 * since the last run. Running a month again pays nobody twice.
 *
 *     php artisan wiifarm:unilevel
 *     php artisan wiifarm:unilevel --month=2026-11
 */
class RunUnilevel extends Command
{
    protected $signature = 'wiifarm:unilevel {--month= : The month to pay, YYYY-MM (default: last month)}';

    protected $description = 'Pay the monthly unilevel bonus';

    public function handle(Unilevel $unilevel): int
    {
        $last = Unilevel::lastClosedMonth();

        if ($this->option('month')) {
            $month = (string) $this->option('month');
            if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
                $this->error('Give the month as YYYY-MM.');

                return self::FAILURE;
            }
            if ($month > $last) {
                $this->error('That month has not ended yet.');

                return self::FAILURE;
            }
            $months = [$month];
        } else {
            $latest = UnilevelRun::where('status', UnilevelRun::DONE)->max('month');
            $months = [];
            $m = $latest ? CarbonImmutable::createFromFormat('Y-m-d', $latest.'-01')->addMonth() : CarbonImmutable::createFromFormat('Y-m-d', $last.'-01');
            for (; $m->format('Y-m') <= $last; $m = $m->addMonth()) {
                $months[] = $m->format('Y-m');
            }
        }

        foreach ($months as $month) {
            $run = $unilevel->run($month);
            $this->info(sprintf('%s: %d member(s), ₦%s earned, ₦%s to the contingency fund, ₦%s held.', $run->month, $run->earners, number_format($run->amount / 100, 2), number_format($run->unpaid / 100, 2), number_format($run->stillHeld() / 100, 2)));
        }
        if ($months === []) {
            $this->info('Nothing to run: last month is already done.');
        }

        return self::SUCCESS;
    }
}
