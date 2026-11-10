<?php

namespace Tests\Feature\Network;

use App\Models\Ledger\Transaction;
use App\Models\Package;
use App\Models\PackageChange;
use App\Models\User;
use App\Services\Network\PlacementException;
use App\Services\Network\PvClimb;
use App\Services\Network\Registration;
use App\Services\Network\Upgrade;

/**
 * PV climbing the tree, joining and upgrades (specification sections 3 and 4).
 */
class PvAndPackagesTest extends NetworkTestCase
{
    public function test_the_seven_packages_are_as_the_specification_says()
    {
        $mega = $this->package('Mega');

        $this->assertSame(45_000_000, $mega->price);
        $this->assertSame(450, $mega->pv);
        $this->assertSame(27_000_000, $mega->pvValue()); // 450 × ₦600
        $this->assertSame(30, $mega->product_packs);
        $this->assertSame(120, $mega->daily_cap_matches);
        $this->assertSame(7, Package::count());
    }

    public function test_pv_climbs_to_every_upline_on_the_leg_the_member_sits_under()
    {
        $a = $this->join('member_a');                       // company L
        $b = $this->join('member_b');                       // company R
        $c = $this->join('member_c', package: 'Builder');   // A L
        $d = $this->join('member_d', package: 'Mega');      // A R

        $company = User::company();
        // Company: A (15) + C (60) + D (450) on the left; B (15) on the right.
        $this->assertSame(525.0, $this->pv($company, 'L'));
        $this->assertSame(15.0, $this->pv($company, 'R'));
        $this->assertSame(525.0, $this->pv($company, 'L', 'lifetime'));
        // A: C on the left, D on the right.
        $this->assertSame(60.0, $this->pv($a, 'L'));
        $this->assertSame(450.0, $this->pv($a, 'R'));
        // Nobody below B or C yet.
        $this->assertSame(0.0, $this->pv($b, 'L') + $this->pv($b, 'R') + $this->pv($c, 'L'));
        $this->artisan('wiifarm:ledger-check')->assertSuccessful();
    }

    public function test_each_pv_credit_names_the_member_the_source_and_the_amount()
    {
        $a = $this->join('member_a', package: 'Starter');

        $tx = Transaction::where('type', PvClimb::SOURCE_REGISTRATION)->sole();
        $this->assertSame('pv:join:'.$a->id, $tx->idempotency_key);
        $this->assertSame('PackageChange', $tx->subject_type);
        $this->assertSame('PV from @member_a', $tx->memo);
        // the company's left lifetime and pairing (+3,000 each) and the PV issued (−6,000)
        $this->assertEqualsCanonicalizing([3000, 3000, -6000], $tx->entries()->pluck('amount')->all());
    }

    public function test_store_pv_counts_for_awards_only()
    {
        $a = $this->join('member_a');
        $x = $this->join('member_x', sponsor: $a);

        app(PvClimb::class)->credit($x, 300_000, PvClimb::SOURCE_STORE, 'store:1');

        // A's left leg: X's 15 PV from joining, plus the store's 3,000 PV as lifetime PV only.
        $this->assertSame(3015.0, $this->pv($a, 'L', 'lifetime'));
        $this->assertSame(15.0, $this->pv($a, 'L', 'pairing'));
    }

    public function test_one_payment_activates_once_only()
    {
        $member = User::factory()->create(['sponsor_id' => User::company()->id]);
        $registration = app(Registration::class);

        $first = $registration->activate($member, $this->package('Starter'), 'paystack:ref_1');
        $again = $registration->activate($member, $this->package('Starter'), 'paystack:ref_1');

        $this->assertSame($first->id, $again->id);
        $this->assertSame(30.0, $this->pv(User::company(), 'L'));

        // A second, different payment for the same member is refused.
        $this->expectException(PlacementException::class);
        $registration->activate($member->fresh(), $this->package('Starter'), 'paystack:ref_2');
    }

    public function test_a_banned_member_cannot_be_activated()
    {
        $member = User::factory()->banned()->create(['sponsor_id' => User::company()->id]);

        $this->expectException(PlacementException::class);
        app(Registration::class)->activate($member, $this->package('Starter'), 'pay:1');
    }

    public function test_an_upgrade_climbs_the_pv_difference_and_changes_the_package()
    {
        $a = $this->join('member_a');
        $x = $this->join('member_x', sponsor: $a, package: 'Starter');
        $upgrade = app(Upgrade::class);

        $this->assertSame(3_000_000, $upgrade->priceDifference($x, $this->package('Builder'))); // ₦60,000 − ₦30,000
        $upgrade->apply($x, $this->package('Builder'), 'pay:upgrade:1');
        $upgrade->apply($x, $this->package('Builder'), 'pay:upgrade:1'); // the same payment again: nothing

        $this->assertSame('Builder', $x->fresh()->package->name);
        $this->assertSame(60.0, $this->pv($a, 'L'));                   // 30 + 30
        $this->assertSame(75.0, $this->pv(User::company(), 'L'));      // A 15 + X 60
        $this->assertSame(2, PackageChange::where('user_id', $x->id)->count());
    }

    public function test_no_downgrades_and_no_upgrades_from_a_locked_package()
    {
        $x = $this->join('member_x', package: 'Builder');
        $upgrade = app(Upgrade::class);

        foreach (['Starter', 'Builder'] as $lower) {
            try {
                $upgrade->apply($x, $this->package($lower), "pay:{$lower}");
                $this->fail("Moved to {$lower}.");
            } catch (PlacementException) {
            }
        }

        $this->package('Builder')->update(['upgradable' => false]);
        $this->expectException(PlacementException::class);
        $upgrade->apply($x->fresh(), $this->package('Mega'), 'pay:mega');
    }
}
