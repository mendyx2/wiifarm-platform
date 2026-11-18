<?php

namespace Tests\Feature\Shop;

use App\Models\Admin;
use App\Models\Affiliate;
use App\Models\Pools\Manufacturer;
use App\Models\Shop\Product;
use App\Models\Stockists\Store;
use App\Services\Ledger\Ledger;
use App\Services\Shop\Orders;
use App\Services\Stockists\Handover;
use App\Services\Stockists\Stock;
use App\Services\Stockists\StoreActivation;
use Illuminate\Support\Str;
use Tests\Feature\Network\NetworkTestCase;

/**
 * Affiliates: their own accounts, links, and commission paid on pickup (specification section 8).
 */
class AffiliatesTest extends NetworkTestCase
{
    private function register(array $data = [])
    {
        return $this->post('/affiliate/register', array_merge([
            'first_name' => 'Kemi', 'last_name' => 'Ade', 'email' => 'kemi@example.com', 'phone' => '0803 123 4567',
            'password' => 'password', 'password_confirmation' => 'password', 'terms' => true,
        ], $data));
    }

    /** A store with stock, and a paid public order of $units brought by $affiliate. */
    private function pickup(?Affiliate $affiliate, int $units = 5): array
    {
        $admin = Admin::factory()->super()->create();
        $owner = $this->join('mega_owner');
        $store = app(StoreActivation::class)->activate(Store::create([
            'user_id' => $owner->id, 'kind' => 'mega', 'sponsor_id' => $owner->sponsor_id, 'name' => 'Mega', 'state' => 'Lagos',
            'address' => '1 Road', 'phone' => '0803', 'status' => 'pending', 'deposit' => config('stockists.kinds.mega.deposit'),
        ]), $admin, 'bank:1');
        $product = Product::where('sku', 'WF-PACK')->firstOrFail();
        app(Stock::class)->allocate($admin, $store, $product, 100, (string) Str::uuid());

        $orders = app(Orders::class);
        $order = $orders->publicOrder([$product->id => $units], ['name' => 'Bola', 'phone' => '0803'], $affiliate);
        $orders->confirmPublicPayment($order, 'ref_'.$units);

        return [$store, $order->fresh()];
    }

    public function test_anyone_can_register_as_an_affiliate_and_gets_a_code()
    {
        $this->get('/affiliate/register')->assertOk();
        $this->register()->assertRedirect('/affiliate');

        $affiliate = Affiliate::where('email', 'kemi@example.com')->sole();
        $this->assertMatchesRegularExpression('/^WII-[A-Z2-9]{6}$/', $affiliate->code);
        $this->assertAuthenticatedAs($affiliate, 'affiliate');
        $this->assertGuest('web');                                   // an affiliate is not a member
        $this->get('/affiliate')->assertInertia(fn ($page) => $page->component('affiliate/dashboard')->where('affiliate.code', $affiliate->code));
    }

    public function test_a_member_can_also_be_an_affiliate_with_a_separate_account()
    {
        $member = $this->join('a_member');
        $this->register(['email' => $member->email])->assertSessionHasNoErrors();

        $this->assertSame(1, Affiliate::where('email', $member->email)->count());
        $this->post('/affiliate/logout');
        $this->register(['email' => $member->email])->assertSessionHasErrors('email'); // but only one affiliate account per email
    }

    public function test_affiliates_sign_in_separately_and_banned_ones_cannot()
    {
        $this->register();
        $this->post('/affiliate/logout');
        $affiliate = Affiliate::where('email', 'kemi@example.com')->sole();

        $this->post('/affiliate/login', ['email' => 'kemi@example.com', 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->post('/affiliate/login', ['email' => 'kemi@example.com', 'password' => 'password'])->assertRedirect('/affiliate');
        $this->post('/affiliate/logout');

        $affiliate->forceFill(['status' => Affiliate::BANNED, 'ban_reason' => 'Spam'])->save();
        $this->post('/affiliate/login', ['email' => 'kemi@example.com', 'password' => 'password'])->assertSessionHasErrors(['email' => 'This affiliate account has been banned: Spam']);
        $this->get('/affiliate')->assertRedirect('/affiliate/login');
    }

    public function test_the_link_remembers_the_affiliate()
    {
        $this->register();
        $code = Affiliate::sole()->code;
        $this->post('/affiliate/logout');

        $this->get('/r/'.strtolower($code))->assertRedirect('/')->assertCookie('wiifarm_ref', $code);
        $this->get('/r/WII-NOSUCH')->assertRedirect('/')->assertCookieMissing('wiifarm_ref');
    }

    public function test_the_commission_is_paid_to_the_affiliate_wallet_on_pickup()
    {
        $affiliate = Affiliate::forceCreate(['code' => 'WII-AAAAAA', 'first_name' => 'Kemi', 'last_name' => 'Ade', 'email' => 'k@example.com', 'phone' => '0803', 'password' => 'password']);
        [$store, $order] = $this->pickup($affiliate, 5);
        $ledger = app(Ledger::class);

        $this->assertSame(0, $ledger->balance($ledger->affiliateWallet($affiliate)));       // held until pickup
        app(Handover::class)->fulfil($store, $order->pickup_code);

        $this->assertSame(50_000, $ledger->balance($ledger->affiliateWallet($affiliate)));  // 5 × ₦100
        $this->assertSame(0, $ledger->balance($ledger->poolAccount('P', 'affiliate')));
        $this->artisan('wiifarm:ledger-check')->assertSuccessful();
    }

    public function test_a_banned_affiliates_commission_goes_to_the_manufacturer()
    {
        $affiliate = Affiliate::forceCreate(['code' => 'WII-BBBBBB', 'first_name' => 'Kemi', 'last_name' => 'Ade', 'email' => 'k@example.com', 'phone' => '0803', 'password' => 'password']);
        [$store, $order] = $this->pickup($affiliate, 2);
        $affiliate->forceFill(['status' => Affiliate::BANNED])->save();
        $ledger = app(Ledger::class);
        $maker = fn () => $ledger->balance($ledger->manufacturerAccount(Manufacturer::firstOrFail()));
        $before = $maker();

        app(Handover::class)->fulfil($store, $order->pickup_code);

        $this->assertSame(0, $ledger->balance($ledger->affiliateWallet($affiliate)));
        $this->assertSame(20_000, $maker() - $before);
    }

    public function test_admins_see_and_ban_affiliates()
    {
        $affiliate = Affiliate::forceCreate(['code' => 'WII-CCCCCC', 'first_name' => 'Kemi', 'last_name' => 'Ade', 'email' => 'k@example.com', 'phone' => '0803', 'password' => 'password']);

        $this->actingAs(Admin::factory()->withPermissions(['affiliates.view'])->create(), 'admin');
        $this->get('/admin/affiliates')->assertInertia(fn ($page) => $page->where('affiliates.data.0.code', 'WII-CCCCCC'));
        $this->post("/admin/affiliates/{$affiliate->id}/standing", ['ban' => true, 'reason' => 'Spam'])->assertForbidden();

        $this->actingAs(Admin::factory()->withPermissions(['affiliates.view', 'affiliates.manage'])->create(), 'admin');
        $this->post("/admin/affiliates/{$affiliate->id}/standing", ['ban' => true, 'reason' => 'Spam'])->assertSessionHasNoErrors();
        $this->assertTrue($affiliate->fresh()->isBanned());
        $this->assertDatabaseHas('audit_logs', ['action' => 'affiliates.ban', 'note' => 'Spam']);
    }
}
