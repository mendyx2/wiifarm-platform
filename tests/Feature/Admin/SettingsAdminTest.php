<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_super_admin_changes_the_verification_switches_and_it_is_logged()
    {
        $admin = Admin::factory()->super()->create();

        $this->actingAs($admin, 'admin')->get('/admin/settings')->assertOk();
        $this->put('/admin/settings', ['verification_email_required' => true, 'verification_mobile_required' => false])->assertSessionHasNoErrors();

        $this->assertTrue(Settings::get('verification.email_required'));
        $this->assertFalse(Settings::get('verification.mobile_required'));
        // only the switch that changed is logged
        $this->assertDatabaseCount('audit_logs', 1);
        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.edit', 'admin_id' => $admin->id]);
        $this->assertDatabaseHas('settings', ['key' => 'verification.email_required', 'updated_by_admin_id' => $admin->id]);
    }

    public function test_other_admins_can_see_but_not_change_settings()
    {
        $this->actingAs(Admin::factory()->withPermissions(['settings.view'])->create(), 'admin');

        $this->get('/admin/settings')->assertOk();
        $this->put('/admin/settings', ['verification_email_required' => true, 'verification_mobile_required' => true])->assertForbidden();

        $this->assertFalse(Settings::get('verification.email_required'));
    }
}
