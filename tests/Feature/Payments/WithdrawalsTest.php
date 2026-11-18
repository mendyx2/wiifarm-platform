<?php

namespace Tests\Feature\Payments;

use App\Models\Admin;
use App\Models\Affiliate;
use App\Models\Payments\Withdrawal;
use App\Models\Payments\WithdrawalMethod;
use App\Models\Stockists\Store;
use App\Models\User;
use App\Services\Ledger\Ledger;
use App\Services\Ledger\LedgerException;
use App\Services\Payments\Withdrawals;
use App\Services\Stockists\StoreActivation;
use App\Support\Settings;
use Illuminate\Support\Str;
use Tests\Feature\Network\NetworkTestCase;

/**
 * Withdrawals and bonus transfer (specification section 6).
 */
class WithdrawalsTest extends NetworkTestCase
{
    private Ledger $ledger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ledger = app(Ledger::class);
        WithdrawalMethod::firstOrFail()->update(['fixed_charge' => 10_000, 'percent_charge' => '1.00']); // ₦100 + 1%
    }

    private function method(): WithdrawalMethod
    {
        return WithdrawalMethod::firstOrFail();
    }

    private function bank(): array
    {
        return ['bank' => 'First Bank', 'account_number' => '3012345678', 'account_name' => 'Amaka Okafor'];
    }

    private function member(int $main = 5_000_000): User
    {
        $member = $this->join('amaka_one');
        $member->forceFill(['kyc_status' => 'verified'])->save();
        if ($main > 0) {
            $this->ledger->transfer($this->ledger->systemAccount('adjustments'), $this->ledger->memberAccount($member, 'main'), $main, 'test', 'fund:'.$member->id);
        }

        return $member->fresh();
    }

    private function request(array $data = [])
    {
        return $this->post('/withdraw', array_merge(['wallet' => 'main', 'method_id' => $this->method()->id, 'amount' => '10,000', 'details' => $this->bank(), 'form_id' => (string) Str::uuid()], $data));
    }

    public function test_a_withdrawal_leaves_the_wallet_at_once_and_is_paid_less_its_charge()
    {
        $member = $this->member();
        $this->actingAs($member)->request()->assertSessionHasNoErrors();

        $w = Withdrawal::sole();
        $this->assertSame([1_000_000, 20_000, 980_000, Withdrawal::PENDING], [$w->amount, $w->charge, $w->payable, $w->status]); // ₦100 + 1% of ₦10,000
        $this->assertSame(4_000_000, $this->ledger->balances($member)['main']);

        $admin = Admin::factory()->withPermissions(['withdrawals.view', 'withdrawals.decide'])->create();
        $this->actingAs($admin, 'admin')->post("/admin/withdrawals/{$w->id}/approve", ['reference' => 'NIP-123'])->assertRedirect('/admin/withdrawals');

        $this->assertSame(Withdrawal::PAID, $w->fresh()->status);
        $this->assertSame(20_000, $this->ledger->balance($this->ledger->cooperativeAccount('withdrawal_charges')));
        $this->assertSame(0, $this->ledger->balance($this->ledger->systemAccount('withdrawals.pending')));
        $this->assertDatabaseHas('audit_logs', ['action' => 'withdrawals.approve', 'note' => 'Reference: NIP-123']);
        $this->artisan('wiifarm:ledger-check')->assertSuccessful();
    }

    public function test_a_refused_withdrawal_returns_everything_and_cannot_be_decided_again()
    {
        $member = $this->member();
        $this->actingAs($member)->request();
        $w = Withdrawal::sole();
        $admin = Admin::factory()->super()->create();

        app(Withdrawals::class)->reject($w, $admin, 'The account name does not match.');

        $this->assertSame(5_000_000, $this->ledger->balances($member)['main']);   // charge included
        $this->assertSame('The account name does not match.', $w->fresh()->reject_reason);
        $this->expectException(LedgerException::class);
        app(Withdrawals::class)->approve($w->fresh(), $admin, 'NIP-1');
    }

    public function test_blocked_members_and_members_without_kyc_cannot_withdraw()
    {
        $member = $this->member();
        $member->forceFill(['withdrawals_blocked' => true, 'withdrawals_block_reason' => 'Under review'])->save();
        $this->actingAs($member->fresh())->request()->assertSessionHasErrors(['amount' => 'Withdrawals are blocked on your account: Under review Contact Wiifarm support.']);

        $member->forceFill(['withdrawals_blocked' => false, 'kyc_status' => 'unverified'])->save();
        $this->actingAs($member->fresh())->request()->assertSessionHasErrors('amount');

        Settings::set('verification.email_required', false); // unrelated switch untouched
        Settings::set('withdrawals.kyc_required', false);
        $this->actingAs($member->fresh())->request()->assertSessionHasNoErrors();
        $this->assertSame(1, Withdrawal::count());
    }

    public function test_limits_details_and_balance_are_checked()
    {
        $member = $this->member(200_000);   // ₦2,000
        $this->actingAs($member);

        $this->request(['amount' => '999'])->assertSessionHasErrors('amount');                        // below ₦1,000
        $this->request(['amount' => '1500', 'details' => ['bank' => 'First Bank']])->assertSessionHasErrors('amount'); // details missing
        $this->request(['amount' => '2500'])->assertSessionHasErrors(['amount' => 'There is not enough in that wallet.']);
        $this->assertSame(0, Withdrawal::count());

        $formId = (string) Str::uuid();
        $this->request(['amount' => '1500', 'form_id' => $formId]);
        $this->request(['amount' => '1500', 'form_id' => $formId]);                                    // sent twice
        $this->assertSame(1, Withdrawal::count());
        $this->assertSame(50_000, $this->ledger->balances($member)['main']);
    }

    public function test_a_stockist_withdraws_from_the_stockist_wallet()
    {
        $owner = $this->member();
        $store = app(StoreActivation::class)->activate(Store::create([
            'user_id' => $owner->id, 'kind' => 'mega', 'sponsor_id' => $owner->sponsor_id, 'name' => 'Mega', 'state' => 'Lagos',
            'address' => '1 Road', 'phone' => '0803', 'status' => 'pending', 'deposit' => config('stockists.kinds.mega.deposit'),
        ]), Admin::factory()->super()->create(), 'bank:1');
        $this->ledger->transfer($this->ledger->systemAccount('adjustments'), $this->ledger->storeWallet($store), 300_000, 'test', 'fund:store');

        $this->actingAs($owner)->get('/withdraw')->assertInertia(fn ($page) => $page->where('wallets.1.key', 'store')->where('wallets.1.balance', 300_000));
        $this->request(['wallet' => 'store', 'amount' => '2000'])->assertSessionHasNoErrors();

        $this->assertSame(100_000, $this->ledger->balance($this->ledger->storeWallet($store)));
        $this->assertSame(Withdrawal::STORE, Withdrawal::sole()->holder_type);
        $this->assertSame(5_000_000, $this->ledger->balances($owner)['main']);   // the member's own balance untouched
    }

    public function test_an_affiliate_withdraws_from_the_affiliate_wallet()
    {
        $affiliate = Affiliate::forceCreate(['code' => 'WII-AAAAAA', 'first_name' => 'Kemi', 'last_name' => 'Ade', 'email' => 'k@example.com', 'phone' => '0803', 'password' => 'password']);
        $this->ledger->transfer($this->ledger->systemAccount('adjustments'), $this->ledger->affiliateWallet($affiliate), 500_000, 'test', 'fund:aff');

        $this->actingAs($affiliate, 'affiliate')->get('/affiliate/withdraw')->assertOk();
        $this->post('/affiliate/withdraw', ['method_id' => $this->method()->id, 'amount' => '5000', 'details' => $this->bank(), 'form_id' => (string) Str::uuid()])->assertSessionHasNoErrors();

        $this->assertSame(0, $this->ledger->balance($this->ledger->affiliateWallet($affiliate)));
        $this->assertSame(Withdrawal::AFFILIATE, Withdrawal::sole()->holder_type);
    }

    public function test_admin_permissions_and_methods()
    {
        $member = $this->member();
        $this->actingAs($member)->request();
        $w = Withdrawal::sole();

        $this->actingAs(Admin::factory()->withPermissions(['withdrawals.view'])->create(), 'admin');
        $this->get('/admin/withdrawals')->assertInertia(fn ($page) => $page->where('withdrawals.data.0.id', $w->id));
        $this->post("/admin/withdrawals/{$w->id}/approve", ['reference' => 'x'])->assertForbidden();
        $this->get('/admin/withdrawals/methods')->assertForbidden();

        $this->actingAs(Admin::factory()->withPermissions(['withdrawals.methods'])->create(), 'admin');
        $this->post('/admin/withdrawals/methods', ['name' => 'USDT', 'min_amount' => '5000', 'max_amount' => '1000', 'fixed_charge' => '0', 'percent_charge' => '0', 'fields' => 'Wallet address', 'is_active' => true])
            ->assertSessionHasErrors('max_amount');
        $this->post('/admin/withdrawals/methods', ['name' => 'USDT', 'min_amount' => '5000', 'max_amount' => '1000000', 'fixed_charge' => '500', 'percent_charge' => '0.5', 'fields' => 'Wallet address', 'is_active' => true])
            ->assertSessionHasNoErrors();
        $usdt = WithdrawalMethod::where('name', 'USDT')->sole();
        $this->assertSame([[['key' => 'wallet_address', 'label' => 'Wallet address']], 50_000], [$usdt->fields, $usdt->fixed_charge]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'withdrawals.method_create']);
    }

    public function test_bonus_transfer_moves_a_bonus_to_the_main_balance()
    {
        $sponsor = $this->member(0);
        $this->join('recruit', sponsor: $sponsor, package: 'Starter');           // ₦4,500 direct bonus
        $this->actingAs($sponsor);

        $this->post('/wallet/transfer', ['wallet' => 'bonus.direct', 'amount' => '5000', 'form_id' => (string) Str::uuid()])->assertSessionHasErrors('transfer');
        $this->post('/wallet/transfer', ['wallet' => 'main', 'amount' => '1', 'form_id' => (string) Str::uuid()])->assertSessionHasErrors('transfer');
        $this->post('/wallet/transfer', ['wallet' => 'bonus.direct', 'amount' => '4500', 'form_id' => (string) Str::uuid()])->assertSessionHasNoErrors();

        $balances = $this->ledger->balances($sponsor);
        $this->assertSame([0, 450_000], [$balances['bonus.direct'], $balances['main']]);
    }
}
