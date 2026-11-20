<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Payments\WithdrawalMethod;
use App\Services\Ledger\Ledger;
use App\Services\Payments\Withdrawals;
use Illuminate\Support\Str;
use Tests\Feature\Network\NetworkTestCase;

/**
 * The admin home page (specification section 11): what is waiting, money
 * today and this month, members and pools, each only for admins allowed.
 */
class AdminDashboardTest extends NetworkTestCase
{
    public function test_the_dashboard_shows_what_is_waiting_the_money_and_the_members()
    {
        $ledger = app(Ledger::class);
        $sponsor = $this->join('sponsor_one');
        $this->join('newbie_one', $sponsor, 'Builder');                 // pays sponsor_one a ₦9,000 direct bonus
        $ledger->transfer($ledger->systemAccount('adjustments'), $ledger->memberAccount($sponsor, 'main'), 2_000_000, 'test', 'fund:1');
        app(Withdrawals::class)->request('member', $sponsor->id, $ledger->memberAccount($sponsor, 'main'), WithdrawalMethod::firstOrFail(), 1_000_000,
            ['bank' => 'First Bank', 'account_number' => '3012345678', 'account_name' => 'Sponsor One'], (string) Str::uuid());

        $this->actingAs(Admin::factory()->super()->create(), 'admin')->get('/admin')->assertInertia(fn ($page) => $page
            ->component('admin/dashboard')
            ->where('todo.1.label', 'Withdrawals to pay or refuse')
            ->where('todo.1.count', 1)
            ->where('money.2.label', 'Bonuses paid to members')
            ->where('money.2.today', fn ($kobo) => $kobo >= 900_000)
            ->where('members.joinedToday', 3)                               // the test's company account is set up today too
            ->where('members.active', 3)                                    // with the company account
            ->where('orders.toCollect', 2)                                  // each member's packs
            ->where('pools.0.code', 'A'));
    }

    public function test_an_admin_sees_only_what_their_role_allows()
    {
        $this->actingAs(Admin::factory()->withPermissions(['kyc.view'])->create(), 'admin')->get('/admin')->assertInertia(fn ($page) => $page
            ->has('todo', 1)
            ->where('todo.0.label', 'Identity checks to review')
            ->where('money', [])
            ->where('members', null)
            ->where('orders', null)
            ->where('pools', null));
    }
}
