<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\CompanyAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The admin's member screens (specification section 11, "Members").
 */
class MemberAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(array $permissions = []): Admin
    {
        return $permissions === [] ? Admin::factory()->super()->create() : Admin::factory()->withPermissions($permissions)->create();
    }

    public function test_the_list_searches_and_filters()
    {
        User::factory()->create(['username' => 'amaka_okafor', 'first_name' => 'Amaka', 'last_name' => 'Okafor']);
        User::factory()->banned()->create(['username' => 'tunde_bello', 'first_name' => 'Tunde', 'last_name' => 'Bello']);
        User::factory()->activated()->create(['username' => 'ngozi_eze', 'first_name' => 'Ngozi', 'last_name' => 'Eze', 'mobile' => '8031234567']);

        $this->actingAs($this->admin(['members.view']), 'admin');

        $names = fn (string $query) => collect($this->get('/admin/members'.$query)->assertOk()->viewData('page')['props']['members']['data'])->pluck('username')->sort()->values()->all();

        $this->assertSame(['amaka_okafor', 'ngozi_eze', 'tunde_bello'], $names(''));
        $this->assertSame(['amaka_okafor'], $names('?q=amaka'));
        $this->assertSame(['amaka_okafor'], $names('?q=Amaka+Okafor'));
        $this->assertSame(['ngozi_eze'], $names('?q=08031234567'));
        $this->assertSame(['tunde_bello'], $names('?status=banned'));
        $this->assertSame(['ngozi_eze'], $names('?paid=paid'));
        $this->assertSame([], $names('?q=%25'));
    }

    public function test_the_member_screens_need_permission()
    {
        $member = User::factory()->create();

        $this->actingAs($this->admin(['kyc.view']), 'admin');
        $this->get('/admin/members')->assertForbidden();
        $this->get("/admin/members/{$member->id}")->assertForbidden();

        $this->actingAs($this->admin(['members.view']), 'admin');
        $this->get("/admin/members/{$member->id}")->assertOk();
        $this->post("/admin/members/{$member->id}/ban", ['reason' => 'x'])->assertForbidden();
        $this->patch("/admin/members/{$member->id}", ['first_name' => 'X'])->assertForbidden();
        $this->post("/admin/members/{$member->id}/sponsor", ['sponsor' => 'x', 'reason' => 'x'])->assertForbidden();
        $this->assertFalse($member->fresh()->isBanned());
    }

    public function test_banning_and_unbanning_are_logged()
    {
        $admin = $this->admin(['members.view', 'members.ban']);
        $member = User::factory()->create();

        $this->actingAs($admin, 'admin')->post("/admin/members/{$member->id}/ban", ['reason' => ''])->assertSessionHasErrors('ban_reason');
        $this->assertFalse($member->fresh()->isBanned());

        $this->post("/admin/members/{$member->id}/ban", ['reason' => 'Fake payment proof'])->assertSessionHasNoErrors();
        $this->assertTrue($member->fresh()->isBanned());
        $this->assertSame('Fake payment proof', $member->fresh()->ban_reason);

        $entry = AuditLog::where('action', 'members.ban')->sole();
        $this->assertSame($admin->id, $entry->admin_id);
        $this->assertSame(['User', $member->id], [$entry->subject_type, $entry->subject_id]);
        $this->assertSame(['status' => 'active', 'ban_reason' => null], $entry->old_values);
        $this->assertSame(['status' => 'banned', 'ban_reason' => 'Fake payment proof'], $entry->new_values);
        $this->assertNotNull($entry->ip_address);

        $this->post("/admin/members/{$member->id}/unban", ['reason' => 'Payment confirmed after all']);
        $this->assertFalse($member->fresh()->isBanned());
        $this->assertNull($member->fresh()->ban_reason);
        $this->assertDatabaseHas('audit_logs', ['action' => 'members.unban', 'note' => 'Payment confirmed after all']);
    }

    public function test_a_banned_member_is_signed_out_on_their_next_request()
    {
        $member = User::factory()->create();
        $this->actingAs($member)->get('/dashboard')->assertOk();

        $this->actingAs($this->admin(), 'admin')->post("/admin/members/{$member->id}/ban", ['reason' => 'Fraud']);

        // A fresh copy from the database, as a real next request would load.
        $this->app['auth']->forgetGuards();
        $this->actingAs($member->fresh(), 'web')->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest('web');
    }

    public function test_the_company_account_cannot_be_banned()
    {
        $this->seed(CompanyAccountSeeder::class);
        $company = User::company();

        $this->actingAs($this->admin(), 'admin')->post("/admin/members/{$company->id}/ban", ['reason' => 'test'])->assertSessionHasErrors('ban_reason');
        $this->assertFalse($company->fresh()->isBanned());
    }

    public function test_blocking_and_unblocking_withdrawals()
    {
        $member = User::factory()->create();
        $this->actingAs($this->admin(['members.view', 'members.block_withdrawals']), 'admin');

        $this->post("/admin/members/{$member->id}/block-withdrawals", ['reason' => 'Under investigation'])->assertSessionHasNoErrors();
        $this->assertTrue($member->fresh()->withdrawals_blocked);
        $this->assertSame('Under investigation', $member->fresh()->withdrawals_block_reason);

        $this->post("/admin/members/{$member->id}/block-withdrawals", ['reason' => 'Again'])->assertSessionHasErrors('block_reason');

        $this->post("/admin/members/{$member->id}/unblock-withdrawals", ['reason' => 'Cleared'])->assertSessionHasNoErrors();
        $this->assertFalse($member->fresh()->withdrawals_blocked);
        $this->assertSame(2, AuditLog::whereIn('action', ['members.block_withdrawals', 'members.unblock_withdrawals'])->count());
        $this->assertSame(['withdrawals_blocked' => false, 'withdrawals_block_reason' => null], AuditLog::where('action', 'members.block_withdrawals')->sole()->old_values);
    }

    public function test_editing_details_records_only_what_changed_and_asks_for_new_contacts_to_be_confirmed()
    {
        $member = User::factory()->create(['email' => 'old@example.com', 'mobile' => '8030000000', 'mobile_verified_at' => now()]);
        $this->actingAs($this->admin(['members.view', 'members.edit']), 'admin');

        $data = [
            'first_name' => $member->first_name, 'last_name' => 'Newname', 'email' => 'new@example.com',
            'dial_code' => '234', 'mobile' => '8039999999', 'country' => 'ng', 'state' => null, 'city' => null, 'address' => null,
        ];

        $this->patch("/admin/members/{$member->id}", $data)->assertSessionHasErrors('note');

        $this->patch("/admin/members/{$member->id}", $data + ['note' => 'Member called; identity checked'])->assertSessionHasNoErrors();

        $member->refresh();
        $this->assertSame(['Newname', 'new@example.com', '8039999999', 'NG'], [$member->last_name, $member->email, $member->mobile, $member->country]);
        $this->assertNull($member->email_verified_at);
        $this->assertNull($member->mobile_verified_at);

        $entry = AuditLog::where('action', 'members.edit')->sole();
        $this->assertEqualsCanonicalizing(['last_name', 'email', 'mobile'], array_keys($entry->new_values));
        $this->assertSame('old@example.com', $entry->old_values['email']);
    }

    public function test_an_email_already_in_use_is_refused()
    {
        User::factory()->create(['email' => 'taken@example.com']);
        $member = User::factory()->create();

        $this->actingAs($this->admin(), 'admin')->patch("/admin/members/{$member->id}", [
            'first_name' => 'A', 'last_name' => 'B', 'email' => 'taken@example.com', 'dial_code' => '234', 'mobile' => '8030000000', 'country' => 'NG', 'note' => 'x',
        ])->assertSessionHasErrors('email');
    }

    public function test_changing_a_sponsor_uses_the_sponsor_rules_and_is_logged()
    {
        $oldSponsor = User::factory()->create();
        $member = User::factory()->create(['sponsor_id' => $oldSponsor->id]);
        $downline = User::factory()->create(['sponsor_id' => $member->id]);
        $newSponsor = User::factory()->create(['username' => 'new_sponsor']);
        $admin = $this->admin(['members.view', 'members.change_sponsor']);

        $this->actingAs($admin, 'admin');
        $this->post("/admin/members/{$member->id}/sponsor", ['sponsor' => 'nobody_here', 'reason' => 'x'])->assertSessionHasErrors('sponsor');
        $this->post("/admin/members/{$member->id}/sponsor", ['sponsor' => $downline->username, 'reason' => 'x'])->assertSessionHasErrors('sponsor');
        $this->assertSame($oldSponsor->id, $member->fresh()->sponsor_id);

        $this->post("/admin/members/{$member->id}/sponsor", ['sponsor' => 'new_sponsor', 'reason' => 'Signed up under the wrong link'])->assertSessionHasNoErrors();

        $this->assertSame($newSponsor->id, $member->fresh()->sponsor_id);
        $this->assertDatabaseHas('sponsor_changes', ['user_id' => $member->id, 'old_sponsor_id' => $oldSponsor->id, 'new_sponsor_id' => $newSponsor->id, 'changed_by_admin_id' => $admin->id]);
        $entry = AuditLog::where('action', 'members.change_sponsor')->sole();
        $this->assertSame(['sponsor_id' => $oldSponsor->id], $entry->old_values);
        $this->assertSame('Signed up under the wrong link', $entry->note);
    }

    public function test_the_detail_page_shows_the_members_history()
    {
        $member = User::factory()->create();
        $this->actingAs($this->admin(), 'admin')->post("/admin/members/{$member->id}/ban", ['reason' => 'Fraud']);

        $this->get("/admin/members/{$member->id}")->assertInertia(fn ($page) => $page
            ->component('admin/members/show')
            ->where('member.status', 'banned')
            ->where('history.0.action', 'members.ban')
            ->where('history.0.note', 'Fraud'));
    }
}
