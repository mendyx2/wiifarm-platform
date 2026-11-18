<?php

namespace Tests\Feature\Members;

use App\Models\User;
use App\Support\Settings;
use Illuminate\Support\Str;
use Tests\Feature\Network\NetworkTestCase;

/** The member dashboard: money, PV, team and what needs doing. */
class DashboardTest extends NetworkTestCase
{
    public function test_someone_who_has_not_paid_is_sent_to_choose_a_package()
    {
        $person = User::factory()->create(['sponsor_id' => User::company()->id]);

        $this->actingAs($person)->get('/dashboard')->assertInertia(fn ($page) => $page->component('dashboard')->where('member', null));
    }

    public function test_a_member_sees_their_money_pv_team_and_what_needs_doing()
    {
        $me = $this->join('dash_member', package: 'Starter');
        $this->join('recruit_left', sponsor: $me, leg: 'L');                  // ₦2,250 direct bonus, 15 PV left
        User::factory()->create(['username' => 'recruit_unpaid', 'sponsor_id' => $me->id]);

        $this->actingAs($me)->get('/dashboard')->assertInertia(fn ($page) => $page
            ->where('member.first_name', $me->first_name)
            ->where('member.package.name', 'Starter')
            ->where('member.referral_link', fn ($link) => str_ends_with($link, '/register?ref=dash_member'))
            ->where('money.bonuses', 225_000)
            ->where('money.earned', 225_000)
            ->where('money.main', 0)
            ->where('pv.L.pairing', 1_500)
            ->where('team.direct', 1)
            ->where('team.waiting', 1)
            ->where('team.size', ['L' => 1, 'R' => 0])
            ->where('toCollect', 1)                                           // their own Starter packs
            ->where('autoship', ['bought' => 0, 'needed' => 1_000, 'hold' => true, 'unilevel' => true])
            ->where('recent.0.wallet', 'Direct referral bonus')
            ->where('recent.0.amount', 225_000)
            ->where('lastMatch', null));

        // Moving the bonus to the main balance does not change what was earned.
        $this->post('/wallet/transfer', ['wallet' => 'bonus.direct', 'amount' => '2250', 'form_id' => (string) Str::uuid()]);
        Settings::set('matching.autoship_hold', false);
        Settings::set('unilevel.activity_required', false);
        $this->get('/dashboard')->assertInertia(fn ($page) => $page
            ->where('money.bonuses', 0)->where('money.earned', 225_000)->where('money.main', 225_000)->where('autoship', null));
    }
}
