<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Services\Compensation\MonthlyActivity;
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
        $this->put('/admin/settings', ['verification_email_required' => true, 'verification_mobile_required' => false, 'withdrawals_kyc_required' => true, 'matching_autoship_hold' => true, 'messages_email' => true, 'site_maintenance' => false])->assertSessionHasNoErrors();

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
        $this->put('/admin/settings', ['verification_email_required' => true, 'verification_mobile_required' => true, 'withdrawals_kyc_required' => true, 'matching_autoship_hold' => true, 'messages_email' => true, 'site_maintenance' => false])->assertForbidden();

        $this->assertFalse(Settings::get('verification.email_required'));
    }

    public function test_the_monthly_purchase_requirement_is_a_setting_and_is_logged()
    {
        $this->assertSame(1_050, MonthlyActivity::requiredPv());   // 10.5 PV by default (Revised Baseline Rules)

        $this->actingAs(Admin::factory()->withPermissions(['settings.view'])->create(), 'admin');
        $this->put('/admin/settings/activity', ['monthly_pv' => '12'])->assertForbidden();

        $this->actingAs(Admin::factory()->super()->create(), 'admin');
        $this->put('/admin/settings/activity', ['monthly_pv' => 'a lot'])->assertSessionHasErrors('monthly_pv');
        $this->put('/admin/settings/activity', ['monthly_pv' => '12.25'])->assertSessionHasNoErrors();

        $this->assertSame(1_225, MonthlyActivity::requiredPv());
        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.edit', 'note' => 'Monthly purchase requirement']);
    }
}
