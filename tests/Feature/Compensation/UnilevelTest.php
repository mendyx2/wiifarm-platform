<?php

namespace Tests\Feature\Compensation;

use App\Models\Pools\UnclaimedBonus;
use App\Models\Shop\Product;
use App\Models\User;
use App\Services\Ledger\Ledger;
use App\Services\Shop\Orders;
use Illuminate\Support\Str;
use Tests\Feature\Network\NetworkTestCase;

/**
 * The unilevel bonus (specification 5.10): paid the moment a member buys
 * products, to 15 generations of their referral chain, each as deep as their
 * package reaches (Revised Baseline Rules).
 */
class UnilevelTest extends NetworkTestCase
{
    private Ledger $ledger;

    /** The shares of ₦6,300 (10.5 PV) for generations 1 to 15: 7%, 5%, 4%, 4%, 3%, 3%, 3%, 2%, 2%, 2%, then 1% for 11 to 15. */
    private const SHARES = [44_100, 31_500, 25_200, 25_200, 18_900, 18_900, 18_900, 12_600, 12_600, 12_600, 6_300, 6_300, 6_300, 6_300, 6_300];

    protected function setUp(): void
    {
        parent::setUp();
        $this->ledger = app(Ledger::class);
    }

    /** $member buys 7 packs (10.5 PV, ₦10,500) from their main balance; returns the form id. */
    private function buys(User $member, ?string $formId = null): string
    {
        $formId ??= (string) Str::uuid();
        $product = Product::where('sku', 'WF-PACK')->firstOrFail();
        $this->ledger->transfer($this->ledger->systemAccount('adjustments'), $this->ledger->memberAccount($member, 'main'), 7 * $product->member_price, 'test.fund', 'fund:'.$formId);
        app(Orders::class)->repurchase($member, [$product->id => 7], 'main', $formId);

        return $formId;
    }

    /** A sponsor chain of $n members: [generation 1, ..., generation n] above the buyer. @return list<User> */
    private function chain(int $n, string $package = 'Mega'): array
    {
        $members = [];
        $sponsor = null;
        for ($i = 1; $i <= $n; $i++) {
            $sponsor = $this->join('gen_'.($n - $i + 1), sponsor: $sponsor, package: $package);
            $members[] = $sponsor;
        }

        return array_reverse($members);
    }

    private function wallet(User $member): int
    {
        return $this->ledger->balances($member)['bonus.unilevel'];
    }

    public function test_fifteen_generations_are_paid_at_once_when_a_member_buys_and_only_once()
    {
        $gens = $this->chain(16);                       // the 16th earns nothing
        $buyer = $this->join('the_buyer', sponsor: $gens[0]);

        $form = $this->buys($buyer);
        app(Orders::class)->repurchase($buyer, [Product::where('sku', 'WF-PACK')->value('id') => 7], 'main', $form);   // the same form again

        foreach (self::SHARES as $i => $share) {
            $this->assertSame($share, $this->wallet($gens[$i]), 'generation '.($i + 1));
        }
        $this->assertSame(0, $this->wallet($gens[15]));
        // The line received ₦2,572.50 (49% of the cooperative's ₦5,250): enough for all 15 generations (₦2,520).
        $this->assertSame(5_250, $this->ledger->balance($this->ledger->poolAccount('C', 'unilevel')));
        $this->artisan('wiifarm:ledger-check')->assertSuccessful();
    }

    public function test_a_package_reaches_only_its_generations_and_the_generation_still_counts()
    {
        $gens = $this->chain(6);
        $gens[4]->forceFill(['package_id' => $this->package('Pre Starter')->id])->save();   // generation 5 on a package that reaches 4
        $before = $this->ledger->balance($this->ledger->poolAccount('C', 'contingency'));

        $buyer = $this->join('the_buyer', sponsor: $gens[0]);
        $this->buys($buyer);

        $this->assertSame(0, $this->wallet($gens[4]));
        $this->assertSame(self::SHARES[5], $this->wallet($gens[5]));   // generation 6 is still paid the 6th rate
        // Generation 5, and 7 to 15 (missing), go to Pool C's Unclaimed Bonus Reserve, each with where it came from;
        // the contingency fund only gets the 15% Pool C keeps anyway (₦787.50).
        $unpaid = 18_900 + 18_900 + 12_600 * 3 + 6_300 * 5;
        $this->assertSame($unpaid, $this->ledger->balance($this->ledger->poolAccount('C', 'unclaimed')));
        $this->assertSame(78_750, $this->ledger->balance($this->ledger->poolAccount('C', 'contingency')) - $before);
        $five = UnclaimedBonus::where(['bonus' => 'unilevel', 'generation' => 5])->sole();
        $this->assertSame(['package_depth', $gens[4]->id, $buyer->id, 18_900], [$five->reason, $five->beneficiary_user_id, $five->source_user_id, $five->amount]);
        $this->assertSame(8, UnclaimedBonus::where(['bonus' => 'unilevel', 'reason' => 'no_upline'])->count());   // generation 7 is the company; 8 to 15 do not exist
        $this->assertSame(1, UnclaimedBonus::where(['bonus' => 'unilevel', 'reason' => 'company'])->count());
    }

    public function test_an_upline_does_not_need_their_own_monthly_purchase()
    {
        $gens = $this->chain(2);                         // neither has bought anything this month
        $this->buys($this->join('the_buyer', sponsor: $gens[0]));

        $this->assertSame([self::SHARES[0], self::SHARES[1]], [$this->wallet($gens[0]), $this->wallet($gens[1])]);
    }

    public function test_banned_and_company_uplines_are_not_paid()
    {
        $gens = $this->chain(2);
        $gens[0]->forceFill(['status' => User::STATUS_BANNED])->save();
        $this->buys($this->join('the_buyer', sponsor: $gens[0]));

        $this->assertSame([0, self::SHARES[1]], [$this->wallet($gens[0]), $this->wallet($gens[1])]);   // generation 3 is the company account
        $this->assertSame(0, $this->wallet(User::company()));
    }
}
