<?php

namespace Tests\Feature\Pools;

use App\Models\Admin;
use App\Models\Pools\HeldPayout;
use App\Models\Pools\Manufacturer;
use App\Models\User;
use App\Services\Ledger\Ledger;
use App\Services\Pools\PaymentSplit;
use App\Services\Pools\PoolPayouts;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Paying from a pool line: the line, then its own contingency fund, otherwise
 * held until it refills, oldest first (specification section 7).
 */
class PoolPayoutsTest extends TestCase
{
    use RefreshDatabase;

    private Ledger $ledger;

    private PoolPayouts $payouts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->ledger = app(Ledger::class);
        $this->payouts = app(PoolPayouts::class);
    }

    private function fundRepurchases(int $units, string $key): void
    {
        app(PaymentSplit::class)->repurchase($units * 150_000, Manufacturer::firstOrFail(), $key);
    }

    private function wallet(User $member): int
    {
        return $this->ledger->balances($member)['bonus.unilevel'];
    }

    private function pay(User $member, int $amount, string $key)
    {
        return $this->payouts->pay('C', 'unilevel', $this->ledger->memberAccount($member, 'bonus.unilevel'), $amount, 'unilevel', $key, 'Unilevel bonus');
    }

    public function test_a_line_with_enough_money_pays_at_once()
    {
        $this->fundRepurchases(1, 'o1'); // unilevel line: ₦367.50
        $amaka = User::factory()->create();

        $this->pay($amaka, 30_000, 'u1');

        $this->assertSame(30_000, $this->wallet($amaka));
        $this->assertSame(6_750, $this->ledger->balance($this->ledger->poolAccount('C', 'unilevel')));
    }

    public function test_the_pools_own_contingency_fund_covers_a_shortfall()
    {
        $this->fundRepurchases(1, 'o1'); // unilevel ₦367.50, contingency ₦112.50
        $amaka = User::factory()->create();

        $this->pay($amaka, 38_700, 'u1'); // ₦387: a full-depth unilevel unit

        $this->assertSame(38_700, $this->wallet($amaka));
        $this->assertSame(0, $this->ledger->balance($this->ledger->poolAccount('C', 'unilevel')));
        $this->assertSame(11_250 - 1_950, $this->ledger->balance($this->ledger->poolAccount('C', 'contingency')));
    }

    public function test_another_pools_money_is_never_used()
    {
        app(PaymentSplit::class)->registration(45_000_000, Manufacturer::firstOrFail(), 'reg:1'); // a full registration pool
        $amaka = User::factory()->create();

        $result = $this->pay($amaka, 100, 'u1'); // the repurchase pool is empty

        $this->assertInstanceOf(HeldPayout::class, $result);
        $this->assertSame(0, $this->wallet($amaka));
    }

    public function test_an_uncovered_payout_is_held_in_full_and_paid_when_money_arrives()
    {
        $amaka = User::factory()->create();

        $held = $this->pay($amaka, 40_000, 'u1');
        $this->assertInstanceOf(HeldPayout::class, $held);
        $this->assertSame(0, $this->wallet($amaka)); // nothing in part

        $this->fundRepurchases(1, 'o1'); // ₦480 now in unilevel + contingency
        $this->assertSame(40_000, $this->wallet($amaka));
        $this->assertSame(HeldPayout::PAID, $held->fresh()->status);
        $this->assertNotNull($held->fresh()->transaction_id);
    }

    public function test_held_payouts_are_paid_oldest_first_and_nobody_jumps_the_queue()
    {
        $first = User::factory()->create();
        $second = User::factory()->create();

        $this->pay($first, 60_000, 'big');     // held: needs ₦600
        $this->pay($second, 1_000, 'small');   // could be covered later, but must wait behind the first

        $this->fundRepurchases(1, 'o1');       // ₦480 arrives: not enough for the first
        $this->assertSame(0, $this->wallet($first));
        $this->assertSame(0, $this->wallet($second));

        $this->fundRepurchases(1, 'o2');       // ₦960 in all
        $this->assertSame(60_000, $this->wallet($first));
        $this->assertSame(1_000, $this->wallet($second));
        $this->assertSame(0, HeldPayout::where('status', HeldPayout::HELD)->count());
    }

    public function test_the_same_payout_is_never_paid_twice()
    {
        $this->fundRepurchases(2, 'o1');
        $amaka = User::factory()->create();

        $this->pay($amaka, 10_000, 'u1');
        $this->pay($amaka, 10_000, 'u1');
        $this->payouts->releaseAll();

        $this->assertSame(10_000, $this->wallet($amaka));
    }

    public function test_an_unpaid_share_stays_in_the_pools_contingency_fund()
    {
        $this->fundRepurchases(1, 'o1');

        $this->payouts->toContingency('C', 'unilevel', 5_000, 'missing-gen-7', 'No 7th generation');

        $this->assertSame(31_750, $this->ledger->balance($this->ledger->poolAccount('C', 'unilevel')));
        $this->assertSame(16_250, $this->ledger->balance($this->ledger->poolAccount('C', 'contingency')));
    }

    public function test_the_hourly_run_pays_what_has_become_affordable()
    {
        $amaka = User::factory()->create();
        $this->pay($amaka, 10_000, 'u1');
        // Money arrives behind the split's back (e.g. a correction), without triggering a release.
        $this->ledger->transfer($this->ledger->systemAccount('adjustments'), $this->ledger->poolAccount('C', 'unilevel'), 10_000, 'test', 'top-up');

        $this->artisan('wiifarm:pay-held')->expectsOutput('1 held payout(s) paid.')->assertSuccessful();
        $this->assertSame(10_000, $this->wallet($amaka));
    }

    public function test_the_pools_page_shows_every_pool_and_needs_permission()
    {
        app(PaymentSplit::class)->registration(1_500_000, Manufacturer::firstOrFail(), 'reg:1');
        $this->pay(User::factory()->create(), 50_000, 'u1'); // held: the repurchase pool is empty

        $this->actingAs(Admin::factory()->withPermissions(['members.view'])->create(), 'admin')->get('/admin/pools')->assertForbidden();

        $this->actingAs(Admin::factory()->withPermissions(['pools.view'])->create(), 'admin')->get('/admin/pools?period=all')
            ->assertInertia(fn ($page) => $page
                ->component('admin/pools')
                ->where('schedule.version', 1)
                ->where('manufacturers.0.balance', 750_000)
                ->where('pools.0.code', 'A')
                ->where('pools.0.balance', 667_500)          // ₦6,502.47 to lines + ₦172.53 contingency
                ->where('held.0.amount', 50_000));
    }
}
