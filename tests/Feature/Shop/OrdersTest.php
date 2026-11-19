<?php

namespace Tests\Feature\Shop;

use App\Models\Admin;
use App\Models\Affiliate;
use App\Models\AuditLog;
use App\Models\Pools\Manufacturer;
use App\Models\Shop\Order;
use App\Models\Shop\Product;
use App\Models\User;
use App\Services\Ledger\InsufficientFunds;
use App\Services\Ledger\Ledger;
use App\Services\Shop\Orders;
use App\Services\Shop\PickupCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Feature\Network\NetworkTestCase;

/**
 * Orders and pickup codes (specification section 8).
 */
class OrdersTest extends NetworkTestCase
{
    private function product(): Product
    {
        return Product::where('sku', 'WF-PACK')->firstOrFail();
    }

    private function fund(User $member, int $kobo, string $wallet = 'main'): void
    {
        $l = app(Ledger::class);
        $l->transfer($l->systemAccount('adjustments'), $l->memberAccount($member, $wallet), $kobo, 'test.fund', 'fund:'.$member->id.':'.$wallet);
    }

    public function test_a_paid_package_comes_with_an_order_for_its_packs()
    {
        $member = $this->join('member_a', package: 'Starter'); // 2 packs

        $order = Order::where('user_id', $member->id)->sole();
        $this->assertSame([Order::REGISTRATION, Order::PAID, 20, 3_000_000, 3000], [$order->kind, $order->status, $order->units, $order->amount, $order->pv]);
        $this->assertMatchesRegularExpression('/^[A-Z2-9]{4}-[A-Z2-9]{4}-[A-Z2-9]{4}$/', $order->pickup_code);
        $this->assertSame('WF0000001', $order->number);
    }

    public function test_a_code_is_found_however_it_is_typed_and_never_stored_in_plain_text()
    {
        $member = $this->join('member_a');
        $order = Order::where('user_id', $member->id)->sole();
        $code = $order->pickup_code;

        $orders = app(Orders::class);
        $this->assertSame($order->id, $orders->findByCode(strtolower(str_replace('-', ' ', $code)))->id);
        $this->assertNull($orders->findByCode('AAAA-BBBB-CCCC'));
        $this->assertNull($orders->findByCode('short'));

        $raw = DB::table('orders')->where('id', $order->id)->first();
        $this->assertStringNotContainsString(PickupCode::normalize($code), $raw->pickup_code);
        $this->assertSame(PickupCode::hash($code), $raw->pickup_code_hash);
        $this->assertNotSame(PickupCode::normalize($code), $raw->pickup_code_hash);
    }

    public function test_a_member_repurchase_is_paid_split_and_its_pv_climbs()
    {
        $sponsor = $this->join('sponsor_a');
        $buyer = $this->join('buyer_b', sponsor: $sponsor);   // on the sponsor's left
        $this->fund($buyer, 3_000_000);
        $ledger = app(Ledger::class);
        $makerBefore = $ledger->balance($ledger->manufacturerAccount(Manufacturer::firstOrFail()));

        $order = app(Orders::class)->repurchase($buyer, [$this->product()->id => 10], 'main', (string) Str::uuid());

        $this->assertSame([Order::REPURCHASE, Order::PAID, 1_500_000, 1500], [$order->kind, $order->status, $order->amount, $order->pv]);
        $this->assertSame(1_500_000, $ledger->balances($buyer)['main']);                                     // ₦15,000 paid
        $this->assertSame(750_000, $ledger->balance($ledger->manufacturerAccount(Manufacturer::firstOrFail())) - $makerBefore);
        // Pool C's unilevel line received ₦3,675 (49% of ₦7,500) and paid the unilevel bonus at once: 7% of the ₦9,000
        // PV value (₦630) to the sponsor; the rest of the 40% (₦2,970) had nobody to receive it and went to the contingency fund.
        $this->assertSame(63_000, $ledger->balances($sponsor)['bonus.unilevel']);
        $this->assertSame(7_500, $ledger->balance($ledger->poolAccount('C', 'unilevel')));
        $this->assertSame(15.0 + 15.0, $this->pv($sponsor, 'L'));                                            // joining + the purchase
        $this->assertSame(1500, $ledger->balance($ledger->repurchasePvAccount($buyer)));                    // 15 PV on their own log
        $this->artisan('wiifarm:ledger-check')->assertSuccessful();
    }

    public function test_the_same_form_sent_twice_buys_once()
    {
        $buyer = $this->join('buyer_b');
        $this->fund($buyer, 3_000_000);
        $formId = (string) Str::uuid();

        $first = app(Orders::class)->repurchase($buyer, [$this->product()->id => 10], 'main', $formId);
        $again = app(Orders::class)->repurchase($buyer, [$this->product()->id => 10], 'main', $formId);

        $this->assertSame($first->id, $again->id);
        $this->assertSame(1_500_000, app(Ledger::class)->balances($buyer)['main']);
    }

    public function test_a_wallet_that_is_short_buys_nothing()
    {
        $buyer = $this->join('buyer_b');
        $this->fund($buyer, 100_000);
        $ordersBefore = Order::count();

        $this->expectException(InsufficientFunds::class);
        try {
            app(Orders::class)->repurchase($buyer, [$this->product()->id => 10], 'main', (string) Str::uuid());
        } finally {
            $this->assertSame($ordersBefore, Order::count());
            $this->assertSame(100_000, app(Ledger::class)->balances($buyer)['main']);
        }
    }

    public function test_a_member_whose_package_is_not_paid_cannot_buy()
    {
        $pending = User::factory()->create(['sponsor_id' => User::company()->id]);

        $this->expectException(\InvalidArgumentException::class);
        app(Orders::class)->repurchase($pending, [$this->product()->id => 1], 'main', (string) Str::uuid());
    }

    public function test_a_public_order_is_paid_online_outside_the_cooperative()
    {
        $orders = app(Orders::class);
        $affiliate = Affiliate::forceCreate(['code' => 'WII-TEST01', 'first_name' => 'Ada', 'last_name' => 'Eze', 'email' => 'ada@example.com', 'phone' => '0803', 'password' => 'password']);
        $order = $orders->publicOrder([$this->product()->id => 3], ['name' => 'Kemi Ade', 'phone' => '08031234567'], $affiliate);
        $this->assertSame([Order::PENDING, 600_000], [$order->status, $order->amount]);
        $this->assertNull($order->pickup_code);

        $orders->confirmPublicPayment($order, 'ref_1');
        $orders->confirmPublicPayment($order->fresh(), 'ref_1'); // the gateway calls twice

        $ledger = app(Ledger::class);
        $this->assertSame(Order::PAID, $order->fresh()->status);
        $this->assertSame(30_000, $ledger->balance($ledger->poolAccount('P', 'affiliate')));  // 20% of 3 × ₦500
        $this->assertSame(36_000, $ledger->balance($ledger->poolAccount('P', 'stockist')));   // 3 × ₦120 held for pickup
        $this->assertSame(0, $ledger->balance($ledger->poolAccount('C', 'unilevel')));
    }

    public function test_members_see_their_own_codes_and_nobody_elses()
    {
        $amaka = $this->join('amaka_one');
        $tunde = $this->join('tunde_two');
        $code = Order::where('user_id', $amaka->id)->sole()->pickup_code;

        $this->actingAs($amaka)->get('/orders')->assertInertia(fn ($page) => $page->where('orders.data.0.pickup_code', $code)->where('orders.total', 1));
        $this->actingAs($tunde)->get('/orders')->assertInertia(fn ($page) => $page->where('orders.total', 1)->where('orders.data.0.pickup_code', fn ($c) => $c !== $code));
    }

    public function test_admins_see_orders_but_never_the_code()
    {
        $amaka = $this->join('amaka_one');
        $order = Order::where('user_id', $amaka->id)->sole();

        $this->actingAs(Admin::factory()->withPermissions(['orders.view'])->create(), 'admin');
        $this->get('/admin/orders')->assertInertia(fn ($page) => $page->where('orders.data.0.number', $order->number));
        $response = $this->get("/admin/orders/{$order->id}");
        $response->assertInertia(fn ($page) => $page->where('order.code_hint', substr(PickupCode::normalize($order->pickup_code), -4)));
        $this->assertStringNotContainsString($order->pickup_code, $response->getContent());
    }

    public function test_buying_through_the_shop_page()
    {
        $buyer = $this->join('buyer_b');
        $this->fund($buyer, 450_000, 'product');

        $this->actingAs($buyer)->get('/shop')->assertInertia(fn ($page) => $page->where('wallets.product', 450_000)->where('canBuy', true));
        $this->post('/shop', ['quantities' => [$this->product()->id => 3], 'wallet' => 'product', 'form_id' => (string) Str::uuid()])
            ->assertRedirect('/orders');

        $this->assertSame(0, app(Ledger::class)->balances($buyer)['product']);
        $this->post('/shop', ['quantities' => [$this->product()->id => 1], 'wallet' => 'product', 'form_id' => (string) Str::uuid()])
            ->assertSessionHasErrors('wallet');
    }

    public function test_products_are_managed_with_permission_and_audited()
    {
        $this->actingAs(Admin::factory()->withPermissions(['orders.view'])->create(), 'admin')->get('/admin/products')->assertForbidden();

        $this->actingAs(Admin::factory()->withPermissions(['products.manage'])->create(), 'admin');
        $data = ['name' => 'Moringa tea', 'sku' => 'wf-tea', 'manufacturer_id' => Manufacturer::firstOrFail()->id, 'member_price' => '1,500', 'public_price' => '2000', 'pv' => '1.5', 'units_per_pack' => 10, 'is_active' => true];

        $this->post('/admin/products', ['public_price' => '1000'] + $data)->assertSessionHasErrors('public_price');
        $this->post('/admin/products', ['pv' => 'lots'] + $data)->assertSessionHasErrors('pv');
        $this->post('/admin/products', $data)->assertSessionHasNoErrors();

        $tea = Product::where('sku', 'WF-TEA')->firstOrFail();
        $this->assertSame([150_000, 200_000, 150], [$tea->member_price, $tea->public_price, $tea->pv]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'products.create', 'subject_id' => $tea->id]);

        $this->put("/admin/products/{$tea->id}", ['member_price' => '1600'] + $data)->assertSessionHasNoErrors();
        $this->assertSame(['member_price' => 160_000], AuditLog::where('action', 'products.edit')->sole()->new_values);
    }
}
