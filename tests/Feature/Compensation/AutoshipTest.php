<?php

namespace Tests\Feature\Compensation;

use App\Models\Shop\Product;
use App\Models\User;
use App\Services\Compensation\Autoship;
use App\Services\Compensation\Matching;
use App\Services\Compensation\MonthlyActivity;
use App\Services\Ledger\Ledger;
use App\Services\Network\PvClimb;
use App\Services\Shop\Orders;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\Feature\Network\NetworkTestCase;

/**
 * The autoship hold (specification 5.6; the owner, after speaking with a
 * leader, 19 November 2026): 20% of matching is held until the month's
 * 10.5 PV purchase, released when it is made, kept by the cooperative if it
 * is not made by the end of the next month's first week.
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

    public function test_the_cooperative_keeps_the_held_share_once_the_first_week_has_passed()
    {
        $this->at('2026-10-20 10:00');
        $member = $this->earner();
        app(Matching::class)->run(CarbonImmutable::parse('2026-10-19'));
        $contingency = fn () => $this->ledger->balance($this->ledger->poolAccount('A', 'contingency'));

        $this->at('2026-11-07 23:00');                                     // still the first week: nothing yet
        $this->artisan('wiifarm:autoship')->assertSuccessful();
        $this->assertSame([576_000, 144_000], $this->wallets($member));

        $before = $contingency();
        $this->at('2026-11-08 03:10');
        $this->artisan('wiifarm:autoship')->assertSuccessful();
        $this->artisan('wiifarm:autoship')->assertSuccessful();          // again: kept once

        $this->assertSame([576_000, 0], $this->wallets($member));
        $this->assertSame(144_000, $contingency() - $before);
        $this->assertDatabaseHas('autoship_settlements', ['month' => '2026-10', 'outcome' => 'kept', 'amount' => 144_000]);
        $this->artisan('wiifarm:ledger-check')->assertSuccessful();
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
