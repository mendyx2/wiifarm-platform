<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_admin_sign_in_page_renders()
    {
        $this->get('/admin/login')->assertOk();
    }

    public function test_signed_out_visitors_are_sent_to_the_admin_sign_in()
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_an_admin_signs_in_with_username_and_password()
    {
        $admin = Admin::factory()->super()->create(['username' => 'chidi_admin']);

        $this->post('/admin/login', ['username' => 'chidi_admin', 'password' => 'password'])
            ->assertRedirect('/admin');

        $this->assertAuthenticatedAs($admin, 'admin');
        $this->assertGuest('web');
        $this->assertNotNull($admin->fresh()->last_sign_in_at);
        $this->assertDatabaseHas('audit_logs', ['admin_id' => $admin->id, 'action' => 'admin.signed_in']);
        $this->get('/admin')->assertOk();
    }

    public function test_a_wrong_password_is_refused()
    {
        Admin::factory()->create(['username' => 'chidi_admin']);

        $this->post('/admin/login', ['username' => 'chidi_admin', 'password' => 'wrong'])->assertSessionHasErrors('username');

        $this->assertGuest('admin');
    }

    public function test_repeated_wrong_passwords_are_slowed_down()
    {
        Admin::factory()->create(['username' => 'chidi_admin']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/admin/login', ['username' => 'chidi_admin', 'password' => 'wrong']);
        }

        // Even the right password is refused until the wait is over.
        $this->post('/admin/login', ['username' => 'chidi_admin', 'password' => 'password'])
            ->assertSessionHasErrors('username');
        $this->assertStringStartsWith('Too many login attempts', session('errors')->first('username'));
        $this->assertGuest('admin');
    }

    public function test_a_member_cannot_sign_in_as_an_admin_or_open_the_panel()
    {
        $member = User::factory()->create(['username' => 'amaka_okafor']);

        $this->post('/admin/login', ['username' => 'amaka_okafor', 'password' => 'password'])->assertSessionHasErrors('username');
        $this->actingAs($member)->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_an_admin_is_not_signed_in_as_a_member()
    {
        Admin::factory()->super()->create(['username' => 'chidi_admin']);

        $this->post('/admin/login', ['username' => 'chidi_admin', 'password' => 'password']);

        $this->assertAuthenticated('admin');
        $this->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest('web');
    }

    public function test_a_deactivated_admin_cannot_sign_in()
    {
        Admin::factory()->deactivated()->create(['username' => 'chidi_admin']);

        $this->post('/admin/login', ['username' => 'chidi_admin', 'password' => 'password'])
            ->assertSessionHasErrors(['username' => 'This admin account has been deactivated.']);
        $this->assertGuest('admin');
    }

    public function test_an_admin_deactivated_while_signed_in_is_signed_out_on_the_next_request()
    {
        $admin = Admin::factory()->super()->create();
        $this->actingAs($admin, 'admin')->get('/admin')->assertOk();

        $admin->forceFill(['is_active' => false])->save();

        $this->get('/admin')->assertRedirect('/admin/login');
        $this->assertGuest('admin');
    }

    public function test_signing_out()
    {
        $admin = Admin::factory()->super()->create();

        $this->actingAs($admin, 'admin')->post('/admin/logout')->assertRedirect('/admin/login');
        $this->assertGuest('admin');
    }

    public function test_a_signed_in_admin_visiting_the_sign_in_page_goes_to_the_dashboard()
    {
        $this->actingAs(Admin::factory()->super()->create(), 'admin')->get('/admin/login')->assertRedirect('/admin');
    }

    public function test_permission_checks_on_routes()
    {
        Route::middleware(['web', 'auth:admin', 'admin.active', 'admin.can:members.ban'])->get('/admin/test-ban', fn () => 'ok');

        $this->actingAs(Admin::factory()->withPermissions(['members.view'])->create(), 'admin')->get('/admin/test-ban')->assertForbidden();
        $this->actingAs(Admin::factory()->withPermissions(['members.view', 'members.ban'])->create(), 'admin')->get('/admin/test-ban')->assertOk();
        $this->actingAs(Admin::factory()->super()->create(), 'admin')->get('/admin/test-ban')->assertOk();
    }

    public function test_the_panel_receives_the_admins_permissions()
    {
        $admin = Admin::factory()->withPermissions(['members.view', 'kyc.view'])->create();

        $this->actingAs($admin, 'admin')->get('/admin')
            ->assertInertia(fn ($page) => $page
                ->component('admin/dashboard')
                ->where('signedInAdmin.isSuper', false)
                ->where('signedInAdmin.permissions', ['members.view', 'kyc.view']));
    }

    public function test_a_super_admin_has_every_permission()
    {
        $admin = Admin::factory()->super()->create();

        $this->assertSame(Role::allPermissions(), $admin->permissions());
        $this->assertTrue($admin->hasPermission('roles.manage'));
    }

    public function test_roles_cannot_hold_super_only_or_unknown_permissions()
    {
        foreach ([['settings.edit'], ['admins.manage'], ['members.view', 'roles.manage'], ['no.such.thing']] as $permissions) {
            try {
                Role::create(['name' => 'Support', 'permissions' => $permissions]);
                $this->fail('A role was saved with '.implode(', ', $permissions));
            } catch (\InvalidArgumentException) {
                // refused, as it should be
            }
        }

        $this->assertSame(0, Role::count());
    }

    public function test_the_audit_log_cannot_be_changed_or_deleted()
    {
        $entry = AuditLog::create(['action' => 'members.ban', 'note' => 'test']);

        try {
            $entry->update(['note' => 'changed']);
            $this->fail('An audit entry was changed.');
        } catch (\LogicException) {
        }

        try {
            $entry->delete();
            $this->fail('An audit entry was deleted.');
        } catch (\LogicException) {
        }

        $this->assertDatabaseHas('audit_logs', ['id' => $entry->id, 'note' => 'test']);
    }
}
