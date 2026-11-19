<?php

namespace Tests\Feature\Compensation;

use App\Models\Admin;
use App\Models\Compensation\MatchRun;
use App\Models\Compensation\MemberMatch;
use App\Models\Pools\HeldPayout;
use App\Models\User;
use App\Services\Compensation\Matching;
use App\Services\Ledger\Ledger;
use App\Services\Network\PvClimb;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Tests\Feature\Network\NetworkTestCase;

/**
 * The daily matching bonus (specification sections 5.5 and 5.6).
 */
class MatchingTest extends NetworkTestCase
{
    private Ledger $ledger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ledger = app(Ledger::class);
    }

    /** A member with exactly $left and $right whole pairing PV, on $package. */
    private function withLegs(string $name, int $left, int $right, string $package = 'Mega'): User
    {
        $top = $this->join($name, package: $package);
        $l = $this->join("{$name}_l", sponsor: $top, leg: 'L');   // 15 PV each
        $r = $this->join("{$name}_r", sponsor: $top, leg: 'R');
        $climb = app(PvClimb::class);
        if ($left > 15) {
            $climb->credit($l, ($left - 15) * 100, PvClimb::SOURCE_PURCHASE, "test:{$name}:l");
        }
        if ($right > 15) {
            $climb->credit($r, ($right - 15) * 100, PvClimb::SOURCE_PURCHASE, "test:{$name}:r");
        }

        return $top->fresh();
    }

    private function wallet(User $member, string $wallet): int
    {
        return $this->ledger->balances($member)[$wallet];
    }

    private function runDay(string $day = '2026-11-17'): MatchRun
    {
        return app(Matching::class)->run(CarbonImmutable::parse($day));
    }

    public function test_the_plans_example_a_mega_member_with_1000_left_and_400_right()
    {
        $member = $this->withLegs('mega', 1000, 400);

        $run = $this->runDay();

        // 8 matches × ₦3,600 = ₦28,800: ₦23,040 for matching and ₦5,760 to autoship.
        $this->assertSame(2_304_000, $this->wallet($member, 'bonus.matching'));
        $this->assertSame(576_000, $this->wallet($member, 'autoship'));
        // 600 PV carries forward on the left and 0 on the right; lifetime PV is untouched.
        $this->assertSame([600.0, 0.0, 1000.0], [$this->pv($member, 'L'), $this->pv($member, 'R'), $this->pv($member, 'L', 'lifetime')]);

        $line = MemberMatch::where('user_id', $member->id)->sole();
        $this->assertSame([8, false, 2_880_000, 576_000, 'paid'], [$line->matches, $line->capped, $line->amount, $line->autoship, $line->status]);
        $this->assertSame([MatchRun::DONE, 2_880_000, 0], [$run->status, $run->amount, $run->held]);
        $this->artisan('wiifarm:ledger-check')->assertSuccessful();
    }

    public function test_the_daily_cap_stops_matches_and_the_rest_carries_forward()
    {
        $member = $this->withLegs('small', 500, 500, 'Pre Starter');   // 10 possible, cap 5

        $this->runDay('2026-11-16');
        $line = MemberMatch::where('user_id', $member->id)->sole();
        $this->assertSame([5, true, 450_000], [$line->matches, $line->capped, $line->amount]);   // 5 × ₦900
        $this->assertSame([250.0, 250.0], [$this->pv($member, 'L'), $this->pv($member, 'R')]);

        // The same day again pays nobody twice; the next day uses what carried forward.
        $this->runDay('2026-11-16');
        $this->assertSame(450_000 * 0.8, (float) $this->wallet($member, 'bonus.matching'));
        $this->runDay('2026-11-17');
        $this->assertSame([0.0, 0.0], [$this->pv($member, 'L'), $this->pv($member, 'R')]);
        $this->assertSame(2, MemberMatch::where('user_id', $member->id)->count());
    }

    public function test_banned_and_company_accounts_are_not_paid_and_keep_their_pv()
    {
        $member = $this->withLegs('banned', 200, 200);
        $member->forceFill(['status' => User::STATUS_BANNED])->save();

        $this->runDay();

        $this->assertSame(0, $this->wallet($member, 'bonus.matching'));
        $this->assertSame([200.0, 200.0], [$this->pv($member, 'L'), $this->pv($member, 'R')]);
        $this->assertSame(0, MemberMatch::count());   // the company account at the top has PV on both legs too
    }

    public function test_no_autoship_share_once_the_monthly_purchase_is_made_or_with_the_switch_off()
    {
        $bought = $this->withLegs('bought', 100, 100);
        $this->ledger->transfer($this->ledger->systemAccount('pv.issued'), $this->ledger->repurchasePvAccount($bought), 1_050, 'test.pv', 'test:bought');   // the 10.5 PV monthly purchase

        $this->runDay(CarbonImmutable::now('Africa/Lagos')->toDateString());
        $this->assertSame([720_000, 0], [$this->wallet($bought, 'bonus.matching'), $this->wallet($bought, 'autoship')]);   // 2 × ₦3,600

        Settings::set('matching.autoship_hold', false);
        $other = $this->withLegs('other', 50, 50);
        $this->runDay('2026-11-01');
        $this->assertSame([360_000, 0], [$this->wallet($other, 'bonus.matching'), $this->wallet($other, 'autoship')]);
    }

    public function test_a_payout_the_matching_line_cannot_cover_is_held_until_it_refills()
    {
        $member = $this->withLegs('big', 10_000, 10_000);    // 120 matches (the cap) × ₦3,600 = ₦432,000

        $run = $this->runDay();

        $line = MemberMatch::where('user_id', $member->id)->sole();
        $this->assertSame([120, MemberMatch::HELD], [$line->matches, $line->status]);
        $this->assertSame(0, $this->wallet($member, 'bonus.matching'));
        $this->assertSame(43_200_000, $run->held);
        $this->assertSame(2, HeldPayout::where('status', HeldPayout::HELD)->where('type', 'matching')->count());
        $this->assertSame([4000.0, 4000.0], [$this->pv($member, 'L'), $this->pv($member, 'R')]);   // the PV is used: the payout is owed

        // Money arrives in the line; the held payouts are paid in full, and the screens show it.
        $this->ledger->transfer($this->ledger->systemAccount('adjustments'), $this->ledger->poolAccount('A', 'matching'), 50_000_000, 'test.fund', 'test:refill');
        $this->artisan('wiifarm:pay-held')->assertSuccessful();
        $this->assertSame([34_560_000, 8_640_000], [$this->wallet($member, 'bonus.matching'), $this->wallet($member, 'autoship')]);
        $this->assertSame(0, $run->fresh()->stillHeld());
        $this->assertSame(MemberMatch::PAID, $line->fresh()->currentStatus());
    }

    public function test_the_command_closes_yesterday_and_catches_up_missed_days()
    {
        $this->withLegs('cmd', 100, 100);
        $yesterday = Matching::lastClosedDay();
        MatchRun::create(['day' => $yesterday->subDays(3)->toDateString(), 'status' => MatchRun::DONE]);

        $this->artisan('wiifarm:match')->assertSuccessful();
        $this->assertSame(
            [$yesterday->subDays(2)->toDateString(), $yesterday->subDay()->toDateString(), $yesterday->toDateString()],
            MatchRun::where('status', MatchRun::DONE)->orderBy('day')->skip(1)->take(3)->pluck('day')->all(),
        );
        $this->artisan('wiifarm:match')->expectsOutputToContain('already done')->assertSuccessful();
        $this->artisan('wiifarm:match', ['--day' => CarbonImmutable::now('Africa/Lagos')->toDateString()])->assertFailed();
    }

    public function test_admins_with_permission_and_the_member_see_the_runs()
    {
        $member = $this->withLegs('seen', 100, 100);
        $run = $this->runDay();

        $this->actingAs(Admin::factory()->withPermissions(['pools.view'])->create(), 'admin');
        $this->get('/admin/matching')->assertForbidden();

        $this->actingAs(Admin::factory()->withPermissions(['matching.view'])->create(), 'admin');
        $this->get('/admin/matching')->assertInertia(fn ($page) => $page->where('runs.data.0.day', '2026-11-17'));
        $this->get("/admin/matching/{$run->id}")->assertInertia(fn ($page) => $page->where('lines.data.0.member.username', 'seen')->where('lines.data.0.matches', 2));

        $this->actingAs($member);
        $this->get('/team/pv')->assertInertia(fn ($page) => $page->where('matches.0.day', '2026-11-17')->where('package.cap', 120)
            ->where('log.data.0.source', 'matching.pv')->where('log.data.0.leg', 'both')->where('log.data.0.pairing', -10_000));   // 100 PV from each leg
    }
}
