<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_log_needs_permission()
    {
        $this->actingAs(Admin::factory()->withPermissions(['members.view'])->create(), 'admin')->get('/admin/audit')->assertForbidden();
        $this->actingAs(Admin::factory()->withPermissions(['audit.view'])->create(), 'admin')->get('/admin/audit')->assertOk();
    }

    public function test_the_log_filters_by_admin_action_and_record()
    {
        $chidi = Admin::factory()->super()->create();
        $bola = Admin::factory()->super()->create();
        $amaka = User::factory()->create();
        $tunde = User::factory()->create();

        $this->actingAs($chidi, 'admin')->post("/admin/members/{$amaka->id}/ban", ['reason' => 'Fraud']);
        $this->actingAs($bola, 'admin')->post("/admin/members/{$tunde->id}/block-withdrawals", ['reason' => 'Check']);
        $this->post("/admin/members/{$amaka->id}/unban", ['reason' => 'Cleared']);

        $actions = fn (string $query) => collect($this->get('/admin/audit'.$query)->assertOk()->viewData('page')['props']['entries']['data'])->pluck('action')->all();

        $this->assertSame(['members.unban', 'members.block_withdrawals', 'members.ban'], $actions(''));
        $this->assertSame(['members.ban'], $actions("?admin={$chidi->id}"));
        $this->assertSame(['members.block_withdrawals'], $actions('?action=members.block_withdrawals'));
        $this->assertSame(['members.unban', 'members.ban'], $actions("?subject_type=User&subject_id={$amaka->id}"));
        $this->assertSame([], $actions('?from=2000-01-01&to=2000-01-02'));
    }

    public function test_there_is_no_way_to_change_or_delete_entries_from_the_panel()
    {
        $routes = collect(app('router')->getRoutes()->getRoutes())->filter(fn ($r) => str_starts_with($r->uri(), 'admin/audit'));

        $this->assertSame([['GET', 'HEAD']], $routes->map(fn ($r) => $r->methods())->values()->all());
    }
}
