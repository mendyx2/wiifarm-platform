<?php

namespace Tests\Feature\Compensation;

use App\Models\Shop\Product;
use App\Models\User;
use App\Notifications\MemberMessage;
use App\Services\Compensation\Autoship;
use App\Services\Compensation\Matching;
use App\Services\Compensation\MonthlyActivity;
use App\Services\Ledger\Ledger;
use App\Services\Network\PvClimb;
use App\Services\Shop\Orders;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\Feature\Network\NetworkTestCase;

/**
 * The autoship hold (specification 5.6; the owner, after speaking with a
 * leader, 19 November 2026): 20% of matching is held until the month's
 * 10.5 PV purchase, released when it is made (or a later month's is), and
 * flushed to Pool A's Unclaimed Bonus Reserve after 3 missed months in a row
 * (the owner, 20 November 2026). Reminders run from the 22nd to the 7th.
 */
class AutoshipTest extends NetworkTestCase
{
    private Ledger $ledger;

    protected function setUp(): void
    {
        // Set everything up in September, so the allocation schedule is in force for the October and November used below.
        Carbon::setTestNow(CarbonImmutable::parse('2026-09-01 09:00', 'Africa/Lagos'));
        parent::setUp();
        $this->ledger = app(Ledger::class);
    }

    private function at(string $lagosTime): void
    {
        $this->travelTo(CarbonImmutable::parse($lagosTime, 'Africa/Lagos'));
    }

    /** A Mega member with 100 PV waiting on each leg: two matches, ₦7,200, of which ₦1,440 would be held. */
    private function earner(): User
    {
        $top = $this->join('earner', package: 'Mega');
        foreach (['L', 'R'] as $leg) {
            $child = $this->join("earner_{$leg}", sponsor: $top, leg: $leg);
            app(PvClimb::class)->credit($child, 8_500, PvClimb::SOURCE_PURCHASE, "t:{$leg}");
        }

        return $top->fresh();
    }

    /** The member buys 7 packs (10.5 PV) from their main balance. */
    private function buys(User $member, int $packs = 7): void
    {
        $product = Product::where('sku', 'WF-PACK')->firstOrFail();
        $this->ledger->transfer($this->ledger->systemAccount('adjustments'), $this->ledger->memberAccount($member, 'main'), $packs * $product->member_price, 'test.fund', 'fund:'.Str::uuid());
        app(Orders::class)->repurchase($member, [$product->id => $packs], 'main', (string) Str::uuid());
    }

    private function wallets(User $member): array
    {
        $b = $this->ledger->balances($member);

        return [$b['bonus.matching'], $b['autoship']];
    }

    public function test_the_held_share_is_released_when_the_month_s_autoship_is_made()
    {
        $this->at('2026-10-05 10:00');
        $member = $this->earner();
        app(Matching::class)->run(CarbonImmutable::parse('2026-10-04'));
        $this->assertSame([576_000, 144_000], $this->wallets($member));   // 20% of ₦7,200 held

        $this->at('2026-10-20 10:00');
        $product = Product::where('sku', 'WF-PACK')->firstOrFail();
        $this->ledger->transfer($this->ledger->systemAccount('adjustments'), $this->ledger->memberAccount($member, 'main'), 7 * $product->member_price, 'test.fund', 'fund:shop');
        $this->actingAs($member)->post('/shop', ['quantities' => [$product->id => 7], 'wallet' => 'main', 'form_id' => (string) Str::uuid()])->assertSessionHasNoErrors();

        $this->assertSame([720_000, 0], $this->wallets($member));          // released at once, on the purchase
        $this->assertDatabaseHas('autoship_settlements', ['user_id' => $member->id, 'month' => '2026-10', 'amount' => 144_000, 'outcome' => 'released']);
        $this->artisan('wiifarm:ledger-check')->assertSuccessful();
    }

    public function test_last_month_s_autoship_can_be_made_in_the_first_week_and_does_not_count_twice()
    {
        $this->at('2026-10-20 10:00');
        $member = $this->earner();
        app(Matching::class)->run(CarbonImmutable::parse('2026-10-19'));

        $this->at('2026-11-03 10:00');
        $this->buys($member);
        app(Autoship::class)->settle($member);

        $this->assertSame([720_000, 0], $this->wallets($member));          // October's held share released
        $activity = app(MonthlyActivity::class);
        $this->assertTrue($activity->fulfilled($member, '2026-10'));
        $this->assertFalse($activity->fulfilled($member, '2026-11'));     // the purchase went to October, not both
        $this->assertSame(0, $activity->creditFor($member, '2026-11'));

        $this->at('2026-11-20 10:00');
        $this->buys($member, 8);                                           // 12 PV in November itself
        $this->assertTrue($activity->fulfilled($member, '2026-11'));
    }

    /** Another 100 PV on each of the earner's legs, for another night of two matches. */
    private function morePv(string $key): void
    {
        foreach (['L', 'R'] as $leg) {
            app(PvClimb::class)->credit(User::where('username', "earner_{$leg}")->firstOrFail(), 10_000, PvClimb::SOURCE_PURCHASE, "{$key}:{$leg}");
        }
    }

    public function test_a_missed_month_stays_held_and_is_released_when_a_later_autoship_is_made()
    {
        $this->at('2026-10-20 10:00');
        $member = $this->earner();
        app(Matching::class)->run(CarbonImmutable::parse('2026-10-19'));

        $this->at('2026-11-08 03:10');                                     // October's first week has passed without it
        $this->artisan('wiifarm:autoship')->assertSuccessful();
        $this->assertSame([576_000, 144_000], $this->wallets($member));   // still held, not kept
        $this->assertSame(1, app(Autoship::class)->missedInARow($member));

        $this->at('2026-11-15 10:00');
        $this->buys($member);                                              // November's autoship
        app(Autoship::class)->settle($member);

        $this->assertSame([720_000, 0], $this->wallets($member));          // October's hold released
        $this->assertDatabaseHas('autoship_settlements', ['month' => '2026-10', 'outcome' => 'released', 'amount' => 144_000]);
        $this->artisan('wiifarm:ledger-check')->assertSuccessful();
    }

    public function test_three_missed_months_in_a_row_flush_their_holds_to_the_unclaimed_bonus_reserve()
    {
        $this->at('2026-10-20 10:00');
        $member = $this->earner();
        app(Matching::class)->run(CarbonImmutable::parse('2026-10-19'));
        $this->at('2026-11-10 10:00');
        $this->morePv('nov');
        app(Matching::class)->run(CarbonImmutable::parse('2026-11-09'));
        $this->assertSame([1_152_000, 288_000], $this->wallets($member)); // October's and November's holds
        $reserve = fn () => $this->ledger->balance($this->ledger->poolAccount('A', Ledger::UNCLAIMED));
        $before = $reserve();

        $this->at('2026-12-08 03:10');                                     // October and November missed: 2 in a row
        $this->artisan('wiifarm:autoship')->assertSuccessful();
        $this->assertSame([1_152_000, 288_000], $this->wallets($member));

        $this->at('2027-01-08 03:10');                                     // December missed too: 3 in a row
        $this->artisan('wiifarm:autoship')->assertSuccessful();
        $this->artisan('wiifarm:autoship')->assertSuccessful();          // again: flushed once

        $this->assertSame([1_152_000, 0], $this->wallets($member));
        $this->assertSame(288_000, $reserve() - $before);
        $this->assertSame(3, app(Autoship::class)->missedInARow($member));
        foreach (['2026-10', '2026-11'] as $month) {
            $this->assertDatabaseHas('autoship_settlements', ['month' => $month, 'outcome' => 'flushed', 'amount' => 144_000]);
            $this->assertDatabaseHas('unclaimed_bonuses', ['pool' => 'A', 'bonus' => 'autoship', 'reason' => 'autoship_missed', 'beneficiary_user_id' => $member->id, 'memo' => "Matching held for {$month}"]);
        }
        $this->artisan('wiifarm:ledger-check')->assertSuccessful();
    }

    public function test_the_month_a_member_joins_counts_and_a_registration_is_not_an_autoship()
    {
        $this->at('2026-10-28 10:00');
        $member = $this->earner();                                         // joined on 28 October with a Mega package
        $this->assertFalse(app(MonthlyActivity::class)->fulfilled($member, '2026-10'));

        $this->at('2026-11-08 03:10');
        $this->assertSame(['2026-10' => 'missed', '2026-11' => 'open'], app(Autoship::class)->statuses($member));
    }

    public function test_reminders_run_from_the_22nd_to_the_7th_once_per_stage()
    {
        Notification::fake();
        $this->at('2026-10-20 10:00');
        $member = $this->earner();
        $others = User::whereNotNull('activated_at')->whereKeyNot($member->id)->get()->reject(fn ($u) => $u->is(User::company()));

        $this->at('2026-10-21 09:20');                                     // before the 4th week
        $this->artisan('wiifarm:autoship-reminders')->expectsOutputToContain('0 autoship')->assertSuccessful();

        $this->at('2026-10-22 09:20');
        $this->artisan('wiifarm:autoship-reminders')->assertSuccessful();
        $this->at('2026-10-29 09:20');                                     // same stage: not again
        $this->artisan('wiifarm:autoship-reminders')->expectsOutputToContain('0 autoship')->assertSuccessful();
        Notification::assertSentTo($member, MemberMessage::class, fn ($n) => $n->event === 'autoship.reminder' && $n->subject === 'Your autoship for October 2026' && str_contains($n->body, 'You have until 7 November'));
        $this->assertSame(1 + $others->count(), DB::table('autoship_reminders')->where('stage', 'week4')->count());

        $this->at('2026-10-25 10:00');                                     // the dashboard shows it too
        $this->actingAs($member)->get('/dashboard')->assertInertia(fn ($page) => $page
            ->where('autoship.reminder.month', 'October 2026')->where('autoship.reminder.deadline', '7 November'));

        $this->at('2026-11-02 09:20');
        $this->artisan('wiifarm:autoship-reminders')->assertSuccessful();
        $this->buys($member);                                              // makes October's autoship
        $this->at('2026-11-07 09:20');
        $this->artisan('wiifarm:autoship-reminders')->assertSuccessful();

        $this->assertSame(1 + $others->count(), DB::table('autoship_reminders')->where(['month' => '2026-10', 'stage' => 'first_week'])->count());
        $this->assertFalse(DB::table('autoship_reminders')->where(['user_id' => $member->id, 'stage' => 'last_day'])->exists());
        $this->assertSame($others->count(), DB::table('autoship_reminders')->where('stage', 'last_day')->count());
        $this->actingAs($member)->get('/dashboard')->assertInertia(fn ($page) => $page->where('autoship.reminder', null));

        $this->at('2026-11-08 09:20');                                     // the window has closed
        $this->artisan('wiifarm:autoship-reminders')->expectsOutputToContain('0 autoship')->assertSuccessful();
    }

    public function test_no_share_is_held_once_the_month_s_autoship_is_made()
    {
        $this->at('2026-10-02 10:00');
        $member = $this->earner();
        $this->buys($member);
        app(Matching::class)->run(CarbonImmutable::parse('2026-10-01'));

        $this->assertSame([720_000, 0], $this->wallets($member));
    }
}
