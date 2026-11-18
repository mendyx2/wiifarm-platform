<?php

namespace Tests\Feature\Pools;

use App\Models\Ledger\Transaction;
use App\Models\Pools\Manufacturer;
use App\Services\Ledger\Ledger;
use App\Services\Pools\PaymentSplit;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Splitting payments by the board's Allocation Blueprint V3 (specification section 7).
 */
class PaymentSplitTest extends TestCase
{
    use RefreshDatabase;

    private Ledger $ledger;

    private PaymentSplit $split;

    private Manufacturer $maker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->ledger = app(Ledger::class);
        $this->split = app(PaymentSplit::class);
        $this->maker = Manufacturer::firstOrFail();
    }

    private function pool(string $pool, string $line): int
    {
        return $this->ledger->balance($this->ledger->poolAccount($pool, $line));
    }

    private function maker(): int
    {
        return $this->ledger->balance($this->ledger->manufacturerAccount($this->maker));
    }

    public function test_a_pre_starter_registration_splits_exactly()
    {
        $this->split->registration(1_500_000, $this->maker, 'pay:1'); // ₦15,000 = 10 units

        $this->assertSame(750_000, $this->maker());                 // ₦750 a unit to the manufacturing company
        // Off the top of the cooperative's ₦7,500: reserve 1%, contingency 2.3%, leaders 10%.
        $this->assertSame(5_250, $this->pool('B', 'recognition'));   // 70% of ₦75
        $this->assertSame(2_250, $this->pool('B', 'ucb'));           // 30% of ₦75
        $this->assertSame(75_000, $this->pool('L', 'held'));
        // ₦6,502.50 net, by its lines (each rounded down to the kobo).
        $this->assertSame(225_116, $this->pool('A', 'direct'));      // covers the plan's ₦2,250 direct bonus
        $this->assertSame(117_045, $this->pool('A', 'indirect'));    // covers ₦1,170 (13%)
        $this->assertSame(18_011, $this->pool('A', 'keyin'));        // covers ₦180 (2%)
        $this->assertSame(108_006, $this->pool('A', 'matching'));
        $this->assertSame(61_773, $this->pool('A', 'leadership'));
        $this->assertSame(120_296, $this->pool('A', 'stockist_rebate')); // covers 10 × ₦120
        // The 2.3% plus what rounding left (3 kobo).
        $this->assertSame(17_253, $this->pool('A', 'contingency'));
        $this->assertSame(-1_500_000, $this->ledger->balance($this->ledger->systemAccount('external')));
        $this->artisan('wiifarm:ledger-check')->assertSuccessful();
    }

    public function test_a_repurchase_keeps_the_full_cooperative_share()
    {
        $this->split->repurchase(150_000, $this->maker, 'order:1'); // one ₦1,500 unit

        $this->assertSame(75_000, $this->maker());
        $this->assertSame(36_750, $this->pool('C', 'unilevel'));
        $this->assertSame(7_500, $this->pool('C', 'repurchase_award'));
        $this->assertSame(7_500, $this->pool('C', 'sub_leaders'));
        $this->assertSame(12_000, $this->pool('C', 'stockist_rebate'));
        $this->assertSame(11_250, $this->pool('C', 'contingency'));
        $this->assertSame(0, $this->pool('B', 'recognition') + $this->pool('L', 'held')); // nothing off the top
    }

    public function test_an_upgrade_splits_into_its_own_pool()
    {
        $this->split->upgrade(3_000_000, $this->maker, 'upgrade:1'); // Starter to Builder: ₦30,000

        $this->assertSame(1_500_000, $this->maker());
        $this->assertSame(10_500, $this->pool('B', 'recognition'));
        $this->assertSame(4_500, $this->pool('B', 'ucb'));
        $this->assertSame(150_000, $this->pool('L', 'held'));
        $this->assertSame(700_875, $this->pool('E', 'upgrade'));     // 52.5% of ₦13,350; the plan pays ₦6,300
        $this->assertSame(634_125, $this->pool('E', 'contingency'));
        $this->assertSame(0, $this->pool('A', 'direct'));            // registration money is never touched
    }

    public function test_a_public_sale_stays_outside_the_cooperative()
    {
        $this->split->publicSale(10, 200_000, $this->maker, viaAffiliate: true, key: 'public:1');

        $this->assertSame(100_000, $this->pool('P', 'affiliate'));   // 20% of the ₦500 margin × 10
        $this->assertSame(120_000, $this->pool('P', 'stockist'));    // ₦120 × 10, held until pickup
        $this->assertSame(1_780_000, $this->maker());

        $this->split->publicSale(1, 200_000, $this->maker, viaAffiliate: false, key: 'public:2');
        $this->assertSame(1_780_000 + 188_000, $this->maker());      // no affiliate: their share stays with the maker

        foreach (['A', 'B', 'C', 'E', 'L'] as $pool) {
            $this->assertSame(0, array_sum(array_map(fn ($l) => $this->pool($pool, $l), array_keys(config("pools.pools.{$pool}.lines")))), "Pool {$pool} received public money");
        }
    }

    public function test_the_same_payment_never_splits_twice()
    {
        $first = $this->split->registration(1_500_000, $this->maker, 'pay:1');
        $again = $this->split->registration(1_500_000, $this->maker, 'pay:1');

        $this->assertTrue($again->wasAlreadyPosted);
        $this->assertSame($first->id, $again->id);
        $this->assertSame(750_000, $this->maker());
        $this->assertSame(1, Transaction::where('type', 'split.registration')->count());
    }

    public function test_every_split_records_the_schedule_version()
    {
        $tx = $this->split->registration(1_500_000, $this->maker, 'pay:1');

        $this->assertSame('Allocation schedule v1', $tx->memo);
    }

    public function test_odd_amounts_still_add_up_exactly()
    {
        foreach ([1, 7, 99_999, 45_000_000, 123_456_789] as $i => $amount) {
            $tx = $this->split->registration($amount, $this->maker, "odd:{$i}");
            $this->assertSame(0, (int) $tx->entries()->sum('amount'));
        }
        $this->artisan('wiifarm:ledger-check')->assertSuccessful();
    }
}
