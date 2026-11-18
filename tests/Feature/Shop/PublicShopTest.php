<?php

namespace Tests\Feature\Shop;

use App\Http\Controllers\PublicShop\PublicShopController;
use App\Models\Admin;
use App\Models\Affiliate;
use App\Models\Payments\OnlinePayment;
use App\Models\Shop\Order;
use App\Models\Shop\Product;
use App\Services\Ledger\Ledger;
use App\Services\Shop\Orders;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Network\NetworkTestCase;

/**
 * The public shop (specification section 8): anyone buys at the public price,
 * pays online with Paystack (faked here), and gets a pickup code.
 */
class PublicShopTest extends NetworkTestCase
{
    private const SECRET = 'sk_test_wiifarm';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.paystack.secret_key' => self::SECRET, 'services.paystack.base_url' => 'https://paystack.test']);
        Http::fake([
            'paystack.test/transaction/initialize' => Http::response(['status' => true, 'data' => ['authorization_url' => 'https://checkout.paystack.test/abc']]),
            'paystack.test/transaction/verify/*' => fn (Request $r) => Http::response(['status' => true, 'data' => [
                'status' => 'success', 'reference' => basename($r->url()), 'amount' => OnlinePayment::where('reference', basename($r->url()))->value('amount'), 'currency' => 'NGN',
            ]]),
        ]);
    }

    private function product(): Product
    {
        return Product::where('sku', 'WF-PACK')->firstOrFail();
    }

    private function affiliate(): Affiliate
    {
        $affiliate = (new Affiliate)->forceFill([
            'code' => Affiliate::newCode(), 'first_name' => 'Kemi', 'last_name' => 'Ade', 'email' => 'kemi@example.com',
            'phone' => '0803', 'password' => 'password', 'status' => Affiliate::ACTIVE,
        ]);
        $affiliate->save();

        return $affiliate;
    }

    /** @param array<string, mixed> $over */
    private function buy(array $over = [], ?Affiliate $via = null)
    {
        $request = $via ? $this->withCookie(PublicShopController::REF_COOKIE, $via->code) : $this;

        return $request->post('/products/order', $over + [
            'name' => 'Bola Ahmed', 'phone' => '0803 555 1234', 'email' => 'bola@example.com', 'quantities' => [$this->product()->id => 3],
        ]);
    }

    private function webhook(OnlinePayment $payment)
    {
        $body = json_encode(['event' => 'charge.success', 'data' => ['status' => 'success', 'reference' => $payment->reference, 'amount' => $payment->amount, 'currency' => 'NGN']]);

        return $this->call('POST', '/webhooks/paystack', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_PAYSTACK_SIGNATURE' => hash_hmac('sha512', $body, self::SECRET)], $body);
    }

    public function test_anyone_sees_the_products_at_the_public_price()
    {
        $this->get('/products')->assertOk()->assertInertia(fn ($page) => $page->component('public/shop')
            ->where('products.0.price', 200_000)->where('referredBy', null)->where('online', true));
    }

    public function test_an_affiliates_link_is_remembered_and_the_order_is_theirs()
    {
        $affiliate = $this->affiliate();
        $this->get('/products?ref='.strtolower($affiliate->code))->assertRedirect('/products')->assertCookie(PublicShopController::REF_COOKIE, $affiliate->code);
        $this->withCookie(PublicShopController::REF_COOKIE, $affiliate->code)->get('/products')->assertInertia(fn ($page) => $page->where('referredBy', 'Kemi Ade'));

        $this->buy(via: $affiliate)->assertRedirect('https://checkout.paystack.test/abc');

        $order = Order::where('kind', Order::PUBLIC)->sole();
        $this->assertSame([$affiliate->id, 600_000, Order::PENDING, 'Bola Ahmed'], [$order->affiliate_id, $order->amount, $order->status, $order->buyer_name]);
        $payment = OnlinePayment::sole();
        $this->assertSame([null, $order->id, 600_000], [$payment->user_id, $payment->order_id, $payment->amount]);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/initialize') && $r['email'] === 'bola@example.com');

        // Paystack confirms: the order is paid and its shares are held until pickup (₦100 a unit for the affiliate).
        $this->webhook($payment)->assertOk();
        $order->refresh();
        $this->assertSame(Order::PAID, $order->status);
        $l = app(Ledger::class);
        $this->assertSame(30_000, $l->balance($l->poolAccount('P', 'affiliate')));
        $this->artisan('wiifarm:ledger-check')->assertSuccessful();
    }

    public function test_the_buyer_comes_back_to_a_private_page_with_the_pickup_code()
    {
        $this->buy()->assertRedirect();
        $payment = OnlinePayment::sole();
        $order = $payment->order;
        $this->assertNull($order->affiliate_id);   // straight from the shop page

        $this->get('/products/paid?reference='.$payment->reference)->assertRedirect(PublicShopController::orderUrl($order));
        $this->get(PublicShopController::orderUrl($order))->assertOk()->assertInertia(fn ($page) => $page->component('public/order')
            ->where('order.status', 'paid')->where('order.code', $order->fresh()->pickup_code)->where('waiting', false));

        // Without the signed link, nobody sees another buyer's order.
        $this->get("/products/order/{$order->id}")->assertForbidden();
        $this->get('/products/paid?reference=WF-NOTHING')->assertRedirect('/products');
    }

    public function test_an_order_needs_products_and_the_buyers_details()
    {
        $this->buy(['quantities' => [$this->product()->id => 0]])->assertSessionHasErrors(['quantities' => 'Choose at least one product.']);
        $this->buy(['phone' => 'call me', 'email' => 'not-an-email', 'name' => ''])->assertSessionHasErrors(['phone', 'email', 'name']);
        $this->assertSame(0, Order::where('kind', Order::PUBLIC)->count());
    }

    public function test_unpaid_orders_are_cancelled_after_the_set_days_but_a_late_payment_is_honoured()
    {
        $this->buy();
        $waiting = OnlinePayment::sole();
        $abandoned = app(Orders::class)->publicOrder([$this->product()->id => 1], ['name' => 'Ada', 'phone' => '0803']);
        Order::whereKey([$waiting->order_id, $abandoned->id])->update(['created_at' => now()->subDays(4)]);

        $this->artisan('wiifarm:expire-public-orders')->assertSuccessful();
        $this->assertSame(Order::CANCELLED, $abandoned->fresh()->status);
        $this->assertSame(Order::PENDING, $waiting->order->fresh()->status);   // Paystack is still confirming it

        $waiting->order->forceFill(['status' => Order::CANCELLED])->save();
        $this->webhook($waiting)->assertOk();
        $this->assertSame(Order::PAID, $waiting->order->fresh()->status);    // paid is paid
    }

    public function test_admins_see_public_payments_with_the_buyer()
    {
        $this->buy();
        $this->actingAs(Admin::factory()->withPermissions(['payments.view'])->create(), 'admin');

        $this->get('/admin/payments')->assertInertia(fn ($page) => $page->where('payments.data.0.member', null)
            ->where('payments.data.0.buyer', 'Bola Ahmed · 0803 555 1234')->where('payments.data.0.what', fn ($w) => str_starts_with($w, 'Public shop order WF')));
    }
}
