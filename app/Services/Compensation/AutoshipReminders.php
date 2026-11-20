<?php

namespace App\Services\Compensation;

use App\Models\User;
use App\Services\Messages\Messages;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Autoship reminders (specification 5.6; the owner, 20 November 2026): from
 * the 4th week of a month (the 22nd) to the end of the next month's first
 * week (the 7th), members who have not yet made that month's autoship are
 * reminded. The month a member joins counts.
 *
 * The dashboard shows the reminder every day of that window. By email, a
 * member is reminded at three moments, once each: when the 4th week starts
 * (week4), when the next month starts (first_week) and on the last day, the
 * 7th (last_day).
 */
class AutoshipReminders
{
    /** The day of the month the 4th week starts. */
    public const WEEK4_STARTS = 22;

    public function __construct(private readonly MonthlyActivity $activity) {}

    /**
     * The month being reminded about at a moment, and the email stage, or
     * null outside the window.
     *
     * @return array{month: string, stage: string, deadline: CarbonImmutable}|null
     */
    public static function window(?CarbonImmutable $at = null): ?array
    {
        $at = ($at ?? CarbonImmutable::now())->setTimezone(config('wiifarm.business_timezone'));
        [$month, $stage] = match (true) {
            $at->day >= self::WEEK4_STARTS => [$at->format('Y-m'), 'week4'],
            $at->day < MonthlyActivity::GRACE_DAYS => [$at->subMonthNoOverflow()->format('Y-m'), 'first_week'],
            $at->day === MonthlyActivity::GRACE_DAYS => [$at->subMonthNoOverflow()->format('Y-m'), 'last_day'],
            default => [null, null],
        };

        return $month === null ? null : ['month' => $month, 'stage' => $stage, 'deadline' => MonthlyActivity::graceEnds($month)->subDay()];
    }

    /** Does this member need reminding about the month? (Joined by then, can earn, and not made yet.) */
    public function due(User $member, string $month): bool
    {
        $monthEnds = CarbonImmutable::createFromFormat('Y-m-d', $month.'-01', config('wiifarm.business_timezone'))->startOfDay()->addMonth();

        return $member->isActivated() && ! $member->isBanned() && ! $member->is(User::company())
            && CarbonImmutable::parse($member->activated_at)->lessThan($monthEnds)
            && ! $this->activity->fulfilled($member, $month);
    }

    /** Sends today's stage to everyone due, once each. Returns how many were sent. */
    public function sendAll(): int
    {
        $window = self::window();
        if ($window === null || ! Settings::get('matching.autoship_hold')) {
            return 0;
        }

        $sent = 0;
        User::whereNotNull('activated_at')->whereNotNull('email')->orderBy('id')->each(function (User $member) use ($window, &$sent) {
            if (! $this->due($member, $window['month'])) {
                return;
            }
            try {
                DB::table('autoship_reminders')->insert([
                    'user_id' => $member->id, 'month' => $window['month'], 'stage' => $window['stage'], 'created_at' => now(),
                ]);
            } catch (UniqueConstraintViolationException) {
                return;   // already reminded at this stage
            }
            $pv = fn (int $hundredths) => rtrim(rtrim(number_format($hundredths / 100, 2), '0'), '.');
            app(Messages::class)->send($member, 'autoship.reminder', [
                'month' => CarbonImmutable::createFromFormat('Y-m-d', $window['month'].'-01')->format('F Y'),
                'deadline' => $window['deadline']->format('j F'),
                'bought' => $pv($this->activity->creditFor($member, $window['month'])),
                'needed' => $pv(MonthlyActivity::requiredPv()),
            ]);
            $sent++;
        });

        return $sent;
    }
}
