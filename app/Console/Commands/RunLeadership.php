<?php

namespace App\Console\Commands;

use App\Models\Compensation\LeadershipRun;
use App\Services\Compensation\Leadership;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * The monthly leadership bonus (specification sections 5.7 and 12). With no
 * month it pays the month just ended, and first any earlier month missed
 * since the last run. Running a month again pays nobody twice.
 *
 *     php artisan wiifarm:leadership
 *     php artisan wiifarm:leadership --month=2026-11
 */
class RunLeadership extends Command
{
    protected $signature = 'wiifarm:leadership {--month= : The month to pay, YYYY-MM (default: last month)}';

    protected $description = 'Pay the monthly leadership bonus';

    public function handle(Leadership $leadership): int
    {
        $last = Leadership::lastClosedMonth();

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
            $latest = LeadershipRun::where('status', LeadershipRun::DONE)->max('month');
            $months = [];
            $m = $latest ? CarbonImmutable::createFromFormat('Y-m-d', $latest.'-01')->addMonth() : CarbonImmutable::createFromFormat('Y-m-d', $last.'-01');
            for (; $m->format('Y-m') <= $last; $m = $m->addMonth()) {
                $months[] = $m->format('Y-m');
            }
        }

        foreach ($months as $month) {
            $run = $leadership->run($month);
            $this->info(sprintf('%s (%s): %d leader(s), ₦%s earned, ₦%s held.', $run->month, $run->team_rule, $run->leaders, number_format($run->amount / 100, 2), number_format($run->stillHeld() / 100, 2)));
        }
        if ($months === []) {
            $this->info('Nothing to run: last month is already done.');
        }

        return self::SUCCESS;
    }
}
