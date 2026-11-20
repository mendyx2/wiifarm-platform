<?php

namespace Tests\Feature\Stockists;

use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\Pools\Manufacturer;
use App\Models\Pools\UnclaimedBonus;
use App\Models\Shop\Order;
use App\Models\Shop\Product;
use App\Models\Stockists\Store;
use App\Models\User;
use App\Services\Ledger\InsufficientFunds;
use App\Services\Ledger\Ledger;
use App\Services\Network\Upgrade;
use App\Services\Shop\Orders;
use App\Services\Stockists\Handover;
use App\Services\Stockists\Stock;
use App\Services\Stockists\StoreActivation;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\Feature\Network\NetworkTestCase;

/**
 * Stockist stores, stock and handing orders over by code (specification section 9).
 */
class StockistsTest extends NetworkTestCase
{
    private Ledger $ledger;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ledger = app(Ledger::class);
        $this->admin = Admin::factory()->super()->create();
    }

    private function product(): Product
    {
        return Product::where('sku', 'WF-PACK')->firstOrFail();
    }

    private function newStore(User $owner, string $kind, ?Store $mega = null): Store
    {
        return Store::create([
            'user_id' => $owner->id, 'kind' => $kind, 'mega_store_id' => $mega?->id, 'sponsor_id' => $owner->sponsor_id,
            'name' => ucfirst($kind).' store of '.$owner->username, 'state' => 'Lagos', 'address' => '1 Market Road', 'phone' => '08030000000',
            'status' => Store::PENDING, 'deposit' => config("stockists.kinds.{$kind}.deposit"),
        ]);
    }

    private function activeStore(User $owner, string $kind, ?Store $mega = null): Store
    {
        return app(StoreActivation::class)->activate($this->newStore($owner, $kind, $mega), $this->admin, 'bank:'.$owner->username);
    }

    private function pool(string $pool, string $line): int
    {
        return $this->ledger->balance($this->ledger->poolAccount($pool, $line));
    }

    /** A paid registration (its money in Pool A) and the member's pack order. */
    private function registeredMember(string $username, string $package = 'Pre Starter'): Order
    {
        $member = $this->join($username, package: $package);

        return Order::where('user_id', $member->id)->where('kind', Order::REGISTRATION)->sole(); // its payment was split on joining
    }

    public function test_activating_a_mega_store_splits_the_deposit_and_pays_the_sponsor_bonus()
    {
        $s2 = $this->join('sponsor_two');                       // under the company
        $s1 = $this->join('sponsor_one', sponsor: $s2);
        $owner = $this->join('mega_owner', sponsor: $s1);
        $makerBefore = $this->ledger->balance($this->ledger->manufacturerAccount(Manufacturer::firstOrFail()));

        $store = $this->activeStore($owner, Store::MEGA);

        $this->assertSame(Store::ACTIVE, $store->status);
        // Half (₦5M) to the manufacturing company for the store's products; the sponsor bonus (6.5%, ₦650,000) from the other half.
        $this->assertSame(500_000_000, $this->ledger->balance($this->ledger->manufacturerAccount(Manufacturer::firstOrFail())) - $makerBefore);
        $this->assertSame(15_000_000, $this->ledger->balances($s1)['bonus.stockist_sponsor']);  // ₦150,000
        $this->assertSame(10_000_000, $this->ledger->balances($s2)['bonus.stockist_sponsor']);  // ₦100,000
        // The rest of the cooperative's half (₦5M − ₦650,000 = ₦4,350,000) stays in Pool D's contingency fund; the ₦400,000
        // nobody could receive (generation 3 is the company account; 4 to 10 do not exist) goes to its Unclaimed Bonus Reserve.
        $this->assertSame(435_000_000, $this->pool('D', 'contingency'));
        $this->assertSame(40_000_000, $this->pool('D', 'unclaimed'));
        $this->assertSame(8, UnclaimedBonus::where(['bonus' => 'stockist_sponsor', 'source_user_id' => $owner->id])->count());
        $this->assertSame(0, $this->pool('D', 'sponsor_bonus'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'stockists.activate', 'subject_id' => $store->id]);
        $this->artisan('wiifarm:ledger-check')->assertSuccessful();
    }

    public function test_store_pv_counts_for_awards_only_up_the_tree_and_the_referral_chain()
    {
        $s1 = $this->join('sponsor_one');
        $owner = $this->join('sub_owner', sponsor: $s1);
        $mega = $this->activeStore($this->join('mega_owner'), Store::MEGA);

        $this->activeStore($owner, Store::SUB, $mega);

        // The owner sits on sponsor_one's left: 3,000 PV of lifetime PV there, and no pairing PV.
        $this->assertSame(3000.0 + 15.0, $this->pv($s1, 'L', 'lifetime'));
        $this->assertSame(15.0, $this->pv($s1, 'L', 'pairing'));
        $this->assertSame(300_000, $this->ledger->balance($this->ledger->teamStorePvAccount($s1)));
    }

    public function test_a_store_is_activated_once_and_a_sub_needs_an_active_mega()
    {
        $mega = $this->newStore($this->join('mega_owner'), Store::MEGA);
        $sub = $this->newStore($this->join('sub_owner'), Store::SUB, $mega);

        try {
            app(StoreActivation::class)->activate($sub, $this->admin, 'bank:1');
            $this->fail('A Sub store was activated before its Mega store.');
        } catch (InvalidArgumentException) {
        }

        app(StoreActivation::class)->activate($mega, $this->admin, 'bank:2');
        $this->expectException(InvalidArgumentException::class);
        app(StoreActivation::class)->activate($mega->fresh(), $this->admin, 'bank:3');
    }

    public function test_stock_is_allocated_to_megas_and_collected_by_their_subs()
    {
        $mega = $this->activeStore($this->join('mega_owner'), Store::MEGA);
        $sub = $this->activeStore($this->join('sub_owner'), Store::SUB, $mega);
        $stock = app(Stock::class);
        $formId = (string) Str::uuid();

        $stock->allocate($this->admin, $mega, $this->product(), 500, $formId);
        $stock->allocate($this->admin, $mega, $this->product(), 500, $formId); // the same form twice
        $stock->collect($mega, $sub, $this->product(), 200, (string) Str::uuid());

        $this->assertSame([$this->product()->id => 300], $stock->levels($mega));
        $this->assertSame([$this->product()->id => 200], $stock->levels($sub));

        try {
            $stock->allocate($this->admin, $sub, $this->product(), 10, (string) Str::uuid());
            $this->fail('Stock was allocated straight to a Sub store.');
        } catch (InvalidArgumentException) {
        }

        $this->expectException(InsufficientFunds::class);
        $stock->collect($mega, $sub, $this->product(), 301, (string) Str::uuid());
    }

    public function test_a_sub_store_hands_over_registration_packs_and_earns_its_rebate()
    {
        $mega = $this->activeStore($this->join('mega_owner'), Store::MEGA);
        $sub = $this->activeStore($this->join('sub_owner'), Store::SUB, $mega);
        app(Stock::class)->allocate($this->admin, $mega, $this->product(), 100, (string) Str::uuid());
        app(Stock::class)->collect($mega, $sub, $this->product(), 50, (string) Str::uuid());
        $order = $this->registeredMember('new_member');                 // 10 units; Pool A's rebate line: ₦1,202.96
        $contingencyBefore = $this->pool('A', 'contingency');

        $preview = app(Handover::class)->preview($sub, $order->pickup_code);
        $this->assertSame([$order->number, 10, 90_000], [$preview['number'], $preview['units'], $preview['rebate']]);

        app(Handover::class)->fulfil($sub, $order->pickup_code);

        $order->refresh();
        $this->assertSame([Order::FULFILLED, $sub->id], [$order->status, $order->fulfilled_by_store_id]);
        $this->assertSame([$this->product()->id => 40], app(Stock::class)->levels($sub));
        $this->assertSame(90_000, $this->ledger->balance($this->ledger->storeWallet($sub)));      // 10 × ₦90
        $this->assertSame(30_000, $this->pool('A', 'contingency') - $contingencyBefore);           // 10 × ₦30 not used
        $this->artisan('wiifarm:ledger-check')->assertSuccessful();

        try {
            app(Handover::class)->fulfil($sub, $order->pickup_code);
            $this->fail('An order was handed over twice.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('already collected', $e->getMessage());
        }
    }

    public function test_a_store_without_enough_stock_hands_over_nothing()
    {
        $mega = $this->activeStore($this->join('mega_owner'), Store::MEGA);
        app(Stock::class)->allocate($this->admin, $mega, $this->product(), 5, (string) Str::uuid());
        $order = $this->registeredMember('new_member');                 // needs 10

        try {
            app(Handover::class)->fulfil($mega, $order->pickup_code);
            $this->fail('An order was handed over without the stock.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Not enough', $e->getMessage());
        }

        $this->assertSame(Order::PAID, $order->fresh()->status);
        $this->assertSame([$this->product()->id => 5], app(Stock::class)->levels($mega));
        $this->assertSame(0, $this->ledger->balance($this->ledger->storeWallet($mega)));
    }

    public function test_a_mega_store_hands_over_a_repurchase_paid_from_the_repurchase_pool()
    {
        $mega = $this->activeStore($this->join('mega_owner'), Store::MEGA);
        app(Stock::class)->allocate($this->admin, $mega, $this->product(), 100, (string) Str::uuid());
        $buyer = $this->join('buyer_b');
        $this->ledger->transfer($this->ledger->systemAccount('adjustments'), $this->ledger->memberAccount($buyer, 'main'), 1_500_000, 'test', 'fund-buyer');
        $order = app(Orders::class)->repurchase($buyer, [$this->product()->id => 10], 'main', (string) Str::uuid());
        $before = $this->pool('C', 'stockist_rebate');

        app(Handover::class)->fulfil($mega, $order->pickup_code);

        $this->assertSame(120_000, $this->ledger->balance($this->ledger->storeWallet($mega)));   // 10 × ₦120
        $this->assertSame(120_000, $before - $this->pool('C', 'stockist_rebate'));
    }

    public function test_a_public_pickup_pays_the_sub_store_and_returns_the_rest_to_the_manufacturer()
    {
        $mega = $this->activeStore($this->join('mega_owner'), Store::MEGA);
        $sub = $this->activeStore($this->join('sub_owner'), Store::SUB, $mega);
        app(Stock::class)->allocate($this->admin, $mega, $this->product(), 10, (string) Str::uuid());
        app(Stock::class)->collect($mega, $sub, $this->product(), 10, (string) Str::uuid());
        $orders = app(Orders::class);
        $order = $orders->publicOrder([$this->product()->id => 4], ['name' => 'Kemi Ade', 'phone' => '0803']);
        $orders->confirmPublicPayment($order, 'ref_1');
        $maker = fn () => $this->ledger->balance($this->ledger->manufacturerAccount(Manufacturer::firstOrFail()));
        $before = $maker();

        app(Handover::class)->fulfil($sub, $order->fresh()->pickup_code);

        $this->assertSame(36_000, $this->ledger->balance($this->ledger->storeWallet($sub)));     // 4 × ₦90
        $this->assertSame(12_000, $maker() - $before);                                            // 4 × ₦30
        $this->assertSame(0, $this->pool('P', 'stockist'));
    }

    public function test_an_upgrade_brings_its_extra_packs_paid_from_the_upgrade_pool()
    {
        $mega = $this->activeStore($this->join('mega_owner'), Store::MEGA);
        app(Stock::class)->allocate($this->admin, $mega, $this->product(), 100, (string) Str::uuid());
        $member = $this->join('upgrader', package: 'Starter');            // 2 packs
        app(Upgrade::class)->apply($member, $this->package('Builder'), 'pay:up:1'); // 4 packs: 2 more; the payment is split

        $order = Order::where('user_id', $member->id)->where('kind', Order::UPGRADE)->sole();
        $this->assertSame([20, 3_000_000, 3000], [$order->units, $order->amount, $order->pv]);

        app(Handover::class)->fulfil($mega, $order->pickup_code);
        $this->assertSame(240_000, $this->ledger->balance($this->ledger->storeWallet($mega)));   // 20 × ₦120, from Pool E's contingency fund
    }

    public function test_a_deactivated_store_cannot_hand_over()
    {
        $mega = $this->activeStore($this->join('mega_owner'), Store::MEGA);
        $order = $this->registeredMember('new_member');
        $mega->forceFill(['status' => Store::DEACTIVATED])->save();

        $this->expectException(InvalidArgumentException::class);
        app(Handover::class)->preview($mega, $order->pickup_code);
    }

    public function test_the_stockist_hands_over_through_their_store_page()
    {
        $owner = $this->join('mega_owner');
        $mega = $this->activeStore($owner, Store::MEGA);
        app(Stock::class)->allocate($this->admin, $mega, $this->product(), 100, (string) Str::uuid());
        $order = $this->registeredMember('new_member');

        $this->actingAs($owner)->get('/store')->assertInertia(fn ($page) => $page->component('store')->where('store.kind', 'mega')->where('stock.0.units', 100));
        $this->post('/store/lookup', ['code' => strtolower($order->pickup_code)])
            ->assertInertia(fn ($page) => $page->where('preview.number', $order->number)->where('preview.units', 10));
        $this->post('/store/handover', ['code' => $order->pickup_code])->assertRedirect('/store')->assertSessionHas('status');
        $this->assertSame(Order::FULFILLED, $order->fresh()->status);

        $this->post('/store/lookup', ['code' => 'AAAA-BBBB-CCCC'])->assertInertia(fn ($page) => $page->where('lookupError', 'No order has that code. Check it and try again.'));
    }

    public function test_admins_set_up_activate_and_allocate_with_the_right_permissions()
    {
        $owner = $this->join('mega_owner');
        $this->actingAs(Admin::factory()->withPermissions(['stockists.view'])->create(), 'admin')->post('/admin/stockists', [])->assertForbidden();

        $this->actingAs(Admin::factory()->withPermissions(['stockists.view', 'stockists.manage'])->create(), 'admin');
        $this->post('/admin/stockists', ['owner' => 'mega_owner', 'kind' => 'mega', 'name' => 'Ikeja Mega', 'state' => 'Lagos', 'address' => '1 Road', 'phone' => '0803'])->assertSessionHasNoErrors();
        $store = Store::where('user_id', $owner->id)->sole();
        $this->post("/admin/stockists/{$store->id}/activate", ['reference' => 'TRF-001'])->assertSessionHasNoErrors();
        $this->assertSame(Store::ACTIVE, $store->fresh()->status);
        $this->post("/admin/stockists/{$store->id}/allocate", ['product_id' => $this->product()->id, 'units' => 10, 'form_id' => (string) Str::uuid()])->assertForbidden();

        $this->actingAs(Admin::factory()->withPermissions(['stockists.view', 'stock.allocate'])->create(), 'admin');
        $this->post("/admin/stockists/{$store->id}/allocate", ['product_id' => $this->product()->id, 'units' => 10, 'form_id' => (string) Str::uuid()])->assertSessionHasNoErrors();
        $this->assertSame([$this->product()->id => 10], app(Stock::class)->levels($store));
        $this->assertSame(1, AuditLog::where('action', 'stock.allocate')->count());
    }
}
