<?php

namespace Tests\Feature\Reports;

use App\Models\Admin;
use App\Models\Payments\WithdrawalMethod;
use App\Services\Ledger\Ledger;
use App\Services\Payments\Withdrawals;
use Illuminate\Support\Str;
use Tests\Feature\Network\NetworkTestCase;

/** The financial report, transactions and login history (specification section 11). */
class ReportsTest extends NetworkTestCase
{
    public function test_the_financial_report_adds_up_the_period()
    {
        $ledger = app(Ledger::class);
        $sponsor = $this->join('sponsor_one');
        $this->join('newbie_one', $sponsor, 'Builder');   // ₦9,000 direct bonus to sponsor_one
        WithdrawalMethod::firstOrFail()->update(['fixed_charge' => 10_000]);
        $ledger->transfer($ledger->systemAccount('adjustments'), $ledger->memberAccount($sponsor, 'main'), 2_000_000, 'test', 'fund:1');
        $withdrawal = app(Withdrawals::class)->request('member', $sponsor->id, $ledger->memberAccount($sponsor, 'main'), WithdrawalMethod::firstOrFail(), 1_000_000,
            ['bank' => 'First Bank', 'account_number' => '3012345678', 'account_name' => 'Sponsor One'], (string) Str::uuid());
        app(Withdrawals::class)->approve($withdrawal, Admin::factory()->create(), 'NIP-1');

        $this->actingAs(Admin::factory()->withPermissions(['members.view'])->create(), 'admin')->get('/admin/reports/financial')->assertForbidden();
        $this->actingAs(Admin::factory()->withPermissions(['reports.view'])->create(), 'admin')->get('/admin/reports/financial?period=today')
            ->assertInertia(fn ($page) => $page
                ->component('admin/reports/financial')
                ->where('withdrawals', ['count' => 1, 'sent' => 990_000, 'charges' => 10_000])
                ->where('bonuses.0.label', 'Direct referral bonus')
                ->where('cooperative.0', ['label' => 'Withdrawal charges', 'amount' => 10_000])
                ->where('members', 3));                          // with the test's company account
        $this->get('/admin/reports/financial?period=custom&from=2020-01-01&to=2020-01-31')->assertInertia(fn ($page) => $page
            ->where('withdrawals.count', 0)->where('members', 0)->where('bonuses', []));
    }

    public function test_transactions_can_be_filtered_by_member_and_kind()
    {
        $sponsor = $this->join('sponsor_one');
        $this->join('newbie_one', $sponsor, 'Builder');
        $this->join('other_two');

        $admin = Admin::factory()->withPermissions(['reports.view'])->create();
        $this->actingAs($admin, 'admin')->get('/admin/reports/transactions?member=newbie_one')->assertInertia(fn ($page) => $page
            ->where('transactions.data', fn ($rows) => collect($rows)->every(fn ($tx) => collect($tx['lines'])->contains(fn ($l) => str_starts_with($l['account'], '@newbie_one')))));
        $this->get('/admin/reports/transactions?type=payout.direct')->assertInertia(fn ($page) => $page
            ->where('transactions.data', fn ($rows) => count($rows) > 0 && collect($rows)->every(fn ($tx) => $tx['type'] === 'payout.direct'))
            ->where('transactions.data.0.lines', fn ($lines) => collect($lines)->sum('amount') === 0));   // every transaction adds up to zero
    }

    public function test_every_sign_in_is_in_the_login_history()
    {
        $member = $this->join('member_one');
        $this->post('/login', ['login' => 'member_one', 'password' => 'password'])->assertRedirect();
        $this->post('/logout');

        $this->actingAs(Admin::factory()->withPermissions(['reports.view'])->create(), 'admin')->get('/admin/reports/logins?guard=web')
            ->assertInertia(fn ($page) => $page->where('logins.data.0.name', 'member_one')->where('logins.data.0.guard', 'web'));
        $this->assertDatabaseHas('login_logs', ['guard' => 'web', 'account_id' => $member->id]);
    }
}
