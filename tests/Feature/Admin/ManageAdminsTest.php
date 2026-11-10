<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Managing admins and roles (specification section 11; super-admins only).
 */
class ManageAdminsTest extends TestCase
{
    use RefreshDatabase;

    private function support(): Role
    {
        return Role::create(['name' => 'Support desk', 'permissions' => ['members.view', 'kyc.view']]);
    }

    public function test_only_super_admins_manage_admins_and_roles()
    {
        // Even an admin given every permission a normal role can hold is refused.
        $notSuper = Admin::factory()->withPermissions(array_values(array_diff(Role::allPermissions(), config('admin_permissions.super_only'))))->create();

        $this->actingAs($notSuper, 'admin');
        $this->get('/admin/admins')->assertForbidden();
        $this->get('/admin/roles')->assertForbidden();
        $this->post('/admin/admins', [])->assertForbidden();
        $this->post('/admin/roles', ['name' => 'x'])->assertForbidden();
    }

    public function test_a_super_admin_adds_an_admin_with_roles()
    {
        $super = Admin::factory()->super()->create();
        $role = $this->support();

        $this->actingAs($super, 'admin')->get('/admin/admins/create')->assertOk();
        $this->post('/admin/admins', [
            'name' => 'Bola Ade', 'username' => 'bola_support', 'email' => 'Bola@Example.com',
            'password' => 'a-long-password-2', 'password_confirmation' => 'a-long-password-2', 'roles' => [$role->id],
        ])->assertSessionHasNoErrors();

        $admin = Admin::where('username', 'bola_support')->firstOrFail();
        $this->assertSame('bola@example.com', $admin->email);
        $this->assertSame(['members.view', 'kyc.view'], $admin->permissions());
        $this->assertTrue(Hash::check('a-long-password-2', $admin->password));

        $entry = AuditLog::where('action', 'admins.create')->sole();
        $this->assertSame(['Support desk'], $entry->new_values['roles']);
        $this->assertArrayNotHasKey('password', $entry->new_values);
    }

    public function test_the_edit_page_keeps_the_signed_in_admin_apart_from_the_admin_being_edited()
    {
        $super = Admin::factory()->super()->create();
        $other = Admin::factory()->withPermissions(['members.view'])->create();

        $this->actingAs($super, 'admin')->get("/admin/admins/{$other->id}")
            ->assertInertia(fn ($page) => $page
                ->component('admin/admins/form')
                ->where('admin.id', $other->id)
                ->where('signedInAdmin.id', $super->id)
                ->where('signedInAdmin.isSuper', true));
    }

    public function test_a_new_admin_needs_a_role_and_a_long_password()
    {
        $this->actingAs(Admin::factory()->super()->create(), 'admin');

        $this->post('/admin/admins', [
            'name' => 'Bola Ade', 'username' => 'bola_support', 'email' => 'bola@example.com',
            'password' => 'short', 'password_confirmation' => 'short', 'roles' => [],
        ])->assertSessionHasErrors(['password', 'roles']);

        $this->assertSame(1, Admin::count());
    }

    public function test_editing_roles_and_password_is_logged_without_the_password()
    {
        $super = Admin::factory()->super()->create();
        $admin = Admin::factory()->create(['name' => 'Bola Ade']);
        $admin->roles()->attach($role = $this->support());
        $other = Role::create(['name' => 'Reviewer', 'permissions' => ['kyc.view', 'kyc.review']]);

        $this->actingAs($super, 'admin')->put("/admin/admins/{$admin->id}", [
            'name' => 'Bola Ade', 'email' => $admin->email, 'password' => 'another-long-pass', 'password_confirmation' => 'another-long-pass',
            'roles' => [$other->id],
        ])->assertSessionHasNoErrors();

        $admin->refresh();
        $this->assertSame(['kyc.view', 'kyc.review'], $admin->permissions());
        $this->assertTrue(Hash::check('another-long-pass', $admin->password));

        $entry = AuditLog::where('action', 'admins.edit')->sole();
        $this->assertSame(['Support desk'], $entry->old_values['roles']);
        $this->assertSame(['Reviewer'], $entry->new_values['roles']);
        $this->assertSame('(changed)', $entry->new_values['password']);
        $this->assertStringNotContainsString('another-long-pass', json_encode([$entry->old_values, $entry->new_values]));
    }

    public function test_there_is_always_an_active_super_admin()
    {
        $only = Admin::factory()->super()->create();
        $role = $this->support();

        // The only super-admin cannot take away their own super-admin role...
        $this->actingAs($only, 'admin')->put("/admin/admins/{$only->id}", [
            'name' => $only->name, 'email' => $only->email, 'roles' => [$role->id],
        ])->assertSessionHasErrors('roles');
        $this->assertTrue($only->fresh()->isSuper());

        // ...but can once another active super-admin exists.
        $second = Admin::factory()->super()->create();
        $this->put("/admin/admins/{$only->id}", ['name' => $only->name, 'email' => $only->email, 'roles' => [$role->id]])->assertSessionHasNoErrors();
        $this->assertFalse($only->fresh()->isSuper());

        // The remaining super-admin cannot be deactivated by anyone.
        $this->actingAs(Admin::factory()->super()->create(), 'admin');
        $this->post("/admin/admins/{$second->id}/deactivate", ['reason' => 'Left the company'])->assertSessionHasNoErrors();
        $this->assertFalse($second->fresh()->isActive());
    }

    public function test_a_deactivated_super_admin_does_not_count_as_another_super_admin()
    {
        $last = Admin::factory()->super()->create();
        Admin::factory()->super()->deactivated()->create();
        $role = $this->support();

        $this->actingAs($last, 'admin')->put("/admin/admins/{$last->id}", [
            'name' => $last->name, 'email' => $last->email, 'roles' => [$role->id],
        ])->assertSessionHasErrors('roles');

        $this->assertTrue($last->fresh()->isSuper());
    }

    public function test_admins_cannot_deactivate_themselves()
    {
        $me = Admin::factory()->super()->create();
        Admin::factory()->super()->create();

        $this->actingAs($me, 'admin')->post("/admin/admins/{$me->id}/deactivate", ['reason' => 'test'])
            ->assertSessionHasErrors(['reason' => 'You cannot deactivate your own account.']);
        $this->assertTrue($me->fresh()->isActive());
    }

    public function test_deactivating_and_reactivating_need_a_reason_and_are_logged()
    {
        $this->actingAs(Admin::factory()->super()->create(), 'admin');
        $admin = Admin::factory()->withPermissions(['members.view'])->create();

        $this->post("/admin/admins/{$admin->id}/deactivate", ['reason' => ''])->assertSessionHasErrors('reason');
        $this->post("/admin/admins/{$admin->id}/deactivate", ['reason' => 'Left the company'])->assertSessionHasNoErrors();
        $this->assertFalse($admin->fresh()->isActive());
        $this->post("/admin/admins/{$admin->id}/activate", ['reason' => 'Rehired']);
        $this->assertTrue($admin->fresh()->isActive());

        $this->assertSame(['admins.deactivate', 'admins.activate'], AuditLog::where('subject_type', 'Admin')->where('subject_id', $admin->id)->orderBy('id')->pluck('action')->all());
    }

    public function test_creating_and_changing_a_role()
    {
        $this->actingAs(Admin::factory()->super()->create(), 'admin');

        $this->post('/admin/roles', ['name' => 'KYC team', 'description' => 'Reviews identity documents', 'permissions' => ['kyc.view', 'kyc.review']])->assertSessionHasNoErrors();
        $role = Role::where('name', 'KYC team')->firstOrFail();
        $this->assertSame(['kyc.view', 'kyc.review'], $role->permissions);

        $this->put("/admin/roles/{$role->id}", ['name' => 'KYC team', 'description' => 'Reviews identity documents', 'permissions' => ['kyc.view']])->assertSessionHasNoErrors();
        $this->assertSame(['kyc.view'], $role->fresh()->permissions);

        $edit = AuditLog::where('action', 'roles.edit')->sole();
        $this->assertSame(['permissions' => ['kyc.view', 'kyc.review']], $edit->old_values);
        $this->assertSame(['permissions' => ['kyc.view']], $edit->new_values);
    }

    public function test_a_role_cannot_get_super_only_permissions_or_change_the_super_role()
    {
        $this->actingAs(Admin::factory()->super()->create(), 'admin');

        $this->post('/admin/roles', ['name' => 'Sneaky', 'permissions' => ['members.view', 'settings.edit']])->assertSessionHasErrors('permissions');
        $this->post('/admin/roles', ['name' => 'Typo', 'permissions' => ['members.everything']])->assertSessionHasErrors('permissions.0');
        $this->assertNull(Role::where('name', 'Sneaky')->first());

        $super = Role::super();
        $this->put("/admin/roles/{$super->id}", ['name' => 'Renamed', 'permissions' => []])->assertSessionHasErrors('permissions');
        $this->assertSame(Role::SUPER, $super->fresh()->name);
    }

    public function test_a_role_in_use_cannot_be_deleted()
    {
        $this->actingAs(Admin::factory()->super()->create(), 'admin');
        $role = $this->support();
        $admin = Admin::factory()->create();
        $admin->roles()->attach($role);

        $this->delete("/admin/roles/{$role->id}")->assertSessionHasErrors('delete');
        $this->assertNotNull($role->fresh());

        $admin->roles()->detach();
        $this->delete("/admin/roles/{$role->id}")->assertRedirect('/admin/roles');
        $this->assertNull($role->fresh());
        $this->assertDatabaseHas('audit_logs', ['action' => 'roles.delete', 'subject_id' => $role->id]);

        $this->delete('/admin/roles/'.Role::super()->id)->assertSessionHasErrors('delete');
    }

    public function test_a_changed_role_applies_on_the_next_click()
    {
        $role = $this->support();
        $admin = Admin::factory()->create();
        $admin->roles()->attach($role);

        $this->actingAs($admin, 'admin')->get('/admin/members')->assertOk();

        $role->update(['permissions' => ['kyc.view']]);
        $this->app['auth']->forgetGuards();

        $this->actingAs($admin->fresh(), 'admin')->get('/admin/members')->assertForbidden();
    }
}
