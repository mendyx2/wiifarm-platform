<?php

namespace Tests\Feature\Compensation;

use App\Models\Admin;
use App\Models\Compensation\UnilevelPayout;
use App\Models\Compensation\UnilevelRun;
use App\Models\Shop\Product;
use App\Models\User;
use App\Services\Compensation\Unilevel;
use App\Services\Ledger\Ledger;
use App\Services\Shop\Orders;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Tests\Feature\Network\NetworkTestCase;

/**
 * The unilevel bonus (specification section 5.10): each month, 10
 * generations up the referral chain share in the PV value of what members bought.
 */
class UnilevelTest extends NetworkTestCase
{
    private Ledger $ledger;

    private string $month;

    /** The shares of ₦6,300 (10.5 PV) for generations 1 to 15: 7%, 5%, 4%, 4%, 3%, 3%, 3%, 2%, 2%, 2%, then 1% for 11 to 15. */
    private const SHARES = [44_100, 31_500, 25_200, 25_200, 18_900, 18_900, 18_900, 12_600, 12_600, 12_600, 6_300, 6_300, 6_300, 6_300, 6_300];

    protected function setUp(): void
    {
        parent::setUp();
        $this->ledger = app(Ledger::class);
        $this->month = CarbonImmutable::now('Africa/Lagos')->format('Y-m');
    }

    /** $member buys 7 packs (10.5 PV, ₦10,500) from their main balance. */
    private function buys(User $member, int $packs = 7): void
    {
        $product = Product::where('sku', 'WF-PACK')->firstOrFail();
        $this->ledger->transfer($this->ledger->systemAccount('adjustments'), $this->ledger->memberAccount($member, 'main'), $packs * $product->member_price, 'test.fund', 'fund:'.Str::uuid());
        app(Orders::class)->repurchase($member, [$product->id => $packs], 'main', (string) Str::uuid());
    }

    /** A sponsor chain of $n members, the top first; the last is the buyer's sponsor. @return list<User> */
    private function chain(int $n, string $package = 'Mega'): array
    {
        $members = [];
        $sponsor = null;
        for ($i = 1; $i <= $n; $i++) {
            $sponsor = $this->join('gen_'.($n - $i + 1), sponsor: $sponsor, package: $package);
            $members[] = $sponsor;
        }

        return array_reverse($members);   // [generation 1, ..., generation n]
    }

    private function wallet(User $member): int
    {
        return $this->ledger->balances($member)['bonus.unilevel'];
    }

    private function runMonth(): UnilevelRun
    {
        return app(Unilevel::class)->run($this->month);
    }

    public function test_fifteen_generations_share_the_pv_value_of_a_purchase_once()
    {
        Settings::set('unilevel.activity_required', false);
        $gens = $this->chain(16);                              // the 16th earns nothing
        $buyer = $this->join('the_buyer', sponsor: $gens[0]);
        $this->buys($buyer);

        $run = $this->runMonth();
        $this->runMonth();                                     // again: pays nobody twice

        foreach (self::SHARES as $i => $share) {
            $this->assertSame($share, $this->wallet($gens[$i]), 'generation '.($i + 1));
        }
        $this->assertSame(0, $this->wallet($gens[15]));
        $this->assertSame([15, 252_000, 1_050], [$run->earners, $run->amount, $run->repurchase_pv]);   // 40% of ₦6,300
        $this->assertSame([1 => 44_100], UnilevelPayout::where('user_id', $gens[0]->id)->sole()->by_generation);
        // The line received ₦2,572.50 (49% of the cooperative's ₦5,250): enough for all 15 generations (₦2,520).
        $this->assertSame(5_250, $this->ledger->balance($this->ledger->poolAccount('C', 'unilevel')));
        $this->artisan('wiifarm:ledger-check')->assertSuccessful();
    }

    public function test_a_package_reaches_only_its_generations_and_the_generation_still_counts()
    {
        Settings::set('unilevel.activity_required', false);
        $gens = $this->chain(6);
        $gens[4]->forceFill(['package_id' => $this->package('Pre Starter')->id])->save();   // generation 5 on a package that reaches 4
        $buyer = $this->join('the_buyer', sponsor: $gens[0]);
        $this->buys($buyer);
        $before = $this->ledger->balance($this->ledger->poolAccount('C', 'contingency'));

        $run = $this->runMonth();

        $this->assertSame(0, $this->wallet($gens[4]));
        $this->assertSame(self::SHARES[5], $this->wallet($gens[5]));            // generation 6 is still paid the 6th rate
        // Generation 5, and 7 to 15 (missing), stay with the cooperative: ₦1,071 moves to the contingency fund.
        $this->assertSame(18_900 + 18_900 + 12_600 * 3 + 6_300 * 5, $run->unpaid);
        $this->assertSame(5_250, $this->ledger->balance($this->ledger->poolAccount('C', 'unilevel')));   // what is never paid out
        $this->assertGreaterThan($before, $this->ledger->balance($this->ledger->poolAccount('C', 'contingency')));
    }

    public function test_with_the_10_pv_rule_only_members_who_bought_this_month_earn()
    {
        $gens = $this->chain(2);
        $buyer = $this->join('the_buyer', sponsor: $gens[0]);
        $this->buys($buyer);
        $this->buys($gens[1]);                                // generation 2 bought their own 10.5 PV; generation 1 did not

        $this->runMonth();

        $this->assertSame(0, $this->wallet($gens[0]));
        $this->assertSame(self::SHARES[1], $this->wallet($gens[1]));
    }

    public function test_banned_and_company_uplines_are_not_paid()
    {
        Settings::set('unilevel.activity_required', false);
        $gens = $this->chain(2);
        $gens[0]->forceFill(['status' => User::STATUS_BANNED])->save();
        $buyer = $this->join('the_buyer', sponsor: $gens[0]);
        $this->buys($buyer);

        $run = $this->runMonth();

        $this->assertSame([0, self::SHARES[1]], [$this->wallet($gens[0]), $this->wallet($gens[1])]);
        $this->assertSame(1, $run->earners);                // generation 3 is the company account: not paid either
    }

    public function test_a_payout_the_unilevel_line_cannot_cover_is_held()
    {
        Settings::set('unilevel.activity_required', false);
        $gens = $this->chain(1);
        $this->buys($this->join('the_buyer', sponsor: $gens[0]));
        foreach (['unilevel', 'contingency'] as $line) {   // empty the repurchase ledger's line and its contingency fund
            $account = $this->ledger->poolAccount('C', $line);
            $this->ledger->transfer($account, $this->ledger->systemAccount('adjustments'), $this->ledger->balance($account), 'test.drain', 'drain:'.$line);
        }

        $run = $this->runMonth();

        $this->assertSame(0, $this->wallet($gens[0]));
        $this->assertSame(UnilevelPayout::HELD, $run->lines()->sole()->status);
        $this->assertSame(44_100, $run->stillHeld());
    }

    public function test_the_command_and_the_admin_pages()
    {
        $this->artisan('wiifarm:unilevel')->assertSuccessful();
        $this->assertSame(Unilevel::lastClosedMonth(), UnilevelRun::sole()->month);
        $this->artisan('wiifarm:unilevel', ['--month' => $this->month])->assertFailed();

        Settings::set('unilevel.activity_required', false);
        $gens = $this->chain(1);
        $this->buys($this->join('the_buyer', sponsor: $gens[0]));
        $run = $this->runMonth();

        $this->actingAs(Admin::factory()->withPermissions(['pools.view'])->create(), 'admin');
        $this->get('/admin/unilevel')->assertForbidden();
        $this->actingAs(Admin::factory()->withPermissions(['matching.view'])->create(), 'admin');
        $this->get('/admin/unilevel')->assertInertia(fn ($page) => $page->where('runs.data.0.month', $this->month));
        $this->get("/admin/unilevel/{$run->id}")->assertInertia(fn ($page) => $page
            ->where('lines.data.0.member.username', 'gen_1')->where('lines.data.0.by_generation.0', ['generation' => 1, 'amount' => 44_100]));
    }
}
