<?php

namespace Tests\Feature\Network;

use App\Models\Admin;
use App\Models\User;
use App\Services\Network\Registration;

/**
 * The genealogy screens, the PV log, the packages page and choosing a place at sign-up.
 */
class NetworkScreensTest extends NetworkTestCase
{
    public function test_a_member_without_a_place_is_told_why()
    {
        $pending = User::factory()->create(['sponsor_id' => User::company()->id]);

        $this->actingAs($pending)->get('/team')->assertInertia(fn ($page) => $page->component('team/tree')->where('placed', false));
    }

    public function test_a_member_sees_their_own_tree_with_pv()
    {
        $a = $this->join('member_a');
        $x = $this->join('member_x', sponsor: $a, package: 'Builder');
        $this->join('member_y', sponsor: $a, package: 'Starter');

        $this->actingAs($a)->get('/team')->assertInertia(fn ($page) => $page
            ->component('team/tree')
            ->where('tree.username', 'member_a')
            ->where('tree.children.L.username', 'member_x')
            ->where('tree.children.R.username', 'member_y')
            ->where('tree.children.L.children.L', null)
            ->where('pv.L.pairing', 6000)
            ->where('pv.R.pairing', 3000));

        // Moving down to X's team is fine.
        $this->get("/team?root={$x->id}")->assertInertia(fn ($page) => $page->where('tree.username', 'member_x')->where('up', $a->id));
    }

    public function test_a_member_cannot_look_above_themselves_or_into_another_team()
    {
        $a = $this->join('member_a');
        $b = $this->join('member_b');
        $x = $this->join('member_x', sponsor: $a);

        $this->actingAs($x)->get("/team?root={$a->id}")->assertNotFound();
        $this->actingAs($x)->get("/team?root={$b->id}")->assertNotFound();
        $this->actingAs($x)->get('/team?root='.User::company()->id)->assertNotFound();
    }

    public function test_the_pv_log_lists_each_credit_with_its_leg_and_source()
    {
        $a = $this->join('member_a');
        $this->join('member_x', sponsor: $a, package: 'Builder');
        $this->join('member_y', sponsor: $a, package: 'Starter');

        $this->actingAs($a)->get('/team/pv')->assertInertia(fn ($page) => $page
            ->component('team/pv')
            ->where('log.total', 2)
            ->where('log.data.0.memo', 'PV from @member_y')
            ->where('log.data.0.leg', 'R')
            ->where('log.data.0.pairing', 3000)
            ->where('log.data.0.lifetime', 3000)
            ->where('log.data.1.leg', 'L'));
    }

    public function test_admins_see_any_members_tree_pv_and_the_packages()
    {
        $a = $this->join('member_a');
        $this->actingAs(Admin::factory()->withPermissions(['members.view', 'settings.view'])->create(), 'admin');

        $this->get("/admin/members/{$a->id}/tree")->assertInertia(fn ($page) => $page->where('tree.username', 'member_a'));
        $this->get("/admin/members/{$a->id}/pv")->assertOk();
        $this->get("/admin/members/{$a->id}")->assertInertia(fn ($page) => $page->where('network.parent.username', 'wiifarm')->where('network.leg', 'L')->where('network.package', 'Pre Starter'));
        $this->get('/admin/packages')->assertInertia(fn ($page) => $page
            ->where('packages.6.name', 'Mega')
            ->where('packages.6.per_match', 360_000)   // 12% of ₦30,000
            ->where('packages.0.per_match', 90_000)    // 3% of ₦30,000
            ->where('packages.0.members', 1));
    }

    public function test_the_packages_page_needs_permission()
    {
        $this->actingAs(Admin::factory()->withPermissions(['members.view'])->create(), 'admin')->get('/admin/packages')->assertForbidden();
    }

    private function signUp(array $data)
    {
        return $this->post('/register', array_merge([
            'first_name' => 'New', 'last_name' => 'Member', 'username' => 'new_member', 'email' => 'new@example.com',
            'dial_code' => '234', 'mobile' => '8031112222', 'country' => 'NG',
            'password' => 'password', 'password_confirmation' => 'password', 'terms' => true,
        ], $data));
    }

    public function test_a_place_can_be_chosen_at_sign_up_and_is_used_when_paid()
    {
        $a = $this->join('member_a');
        $under = $this->join('under_a', sponsor: $a);

        $this->signUp(['sponsor' => 'member_a', 'parent' => 'under_a', 'leg' => 'R'])->assertSessionHasNoErrors();

        $member = User::where('username', 'new_member')->firstOrFail();
        $this->assertSame([$under->id, 'R'], [$member->requested_parent_id, $member->requested_leg]);

        app(Registration::class)->activate($member, $this->package('Starter'), 'pay:new');
        $this->assertSame(['under_a', 'R'], $this->placeOf($member->fresh()));
    }

    public function test_a_place_outside_the_sponsors_team_or_already_taken_is_refused_at_sign_up()
    {
        $a = $this->join('member_a');
        $b = $this->join('member_b');
        $this->join('left_of_a', sponsor: $a, leg: 'L');

        $this->signUp(['sponsor' => 'member_a', 'parent' => 'member_b'])->assertSessionHasErrors('parent');
        $this->signUp(['sponsor' => 'member_a', 'parent' => 'nobody_here'])->assertSessionHasErrors('parent');
        $this->signUp(['sponsor' => 'member_a', 'leg' => 'L'])->assertSessionHasErrors('leg');
        $this->signUp(['sponsor' => 'member_a', 'leg' => 'X'])->assertSessionHasErrors('leg');
        $this->assertNull(User::where('username', 'new_member')->first());

        $this->signUp(['sponsor' => 'member_a', 'leg' => 'R'])->assertSessionHasNoErrors();
    }
}
