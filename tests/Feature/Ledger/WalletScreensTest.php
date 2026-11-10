<?php

namespace Tests\Feature\Ledger;

use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Ledger\Ledger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The member's wallet page, and admins adding to or subtracting from a wallet.
 */
class WalletScreensTest extends TestCase
{
    use RefreshDatabase;

    private function adjust(User $member, array $data = [])
    {
        return $this->post("/admin/members/{$member->id}/wallets", array_merge([
            'wallet' => 'main', 'direction' => 'add', 'amount' => '1,500.50', 'reason' => 'Refund of a double charge', 'form_id' => (string) Str::uuid(),
        ], $data));
    }

    public function test_an_admin_adds_to_a_wallet_and_it_is_in_the_ledger_and_the_audit_log()
    {
        $admin = Admin::factory()->withPermissions(['members.view', 'members.adjust_wallet'])->create();
        $member = User::factory()->create();

        $this->actingAs($admin, 'admin');
        $this->adjust($member)->assertSessionHasNoErrors();

        $this->assertSame(150_050, app(Ledger::class)->balances($member)['main']);
        $entry = AuditLog::where('action', 'members.wallet_add')->sole();
        $this->assertSame(['wallet' => 'main', 'balance' => 0], $entry->old_values);
        $this->assertSame(150_050, $entry->new_values['balance']);
        $this->assertSame('Refund of a double charge', $entry->note);
        $this->artisan('wiifarm:ledger-check')->assertSuccessful();
    }

    public function test_the_same_form_sent_twice_posts_once()
    {
        $this->actingAs(Admin::factory()->super()->create(), 'admin');
        $member = User::factory()->create();
        $formId = (string) Str::uuid();

        $this->adjust($member, ['form_id' => $formId]);
        $this->adjust($member, ['form_id' => $formId]);

        $this->assertSame(150_050, app(Ledger::class)->balances($member)['main']);
        $this->assertSame(1, AuditLog::where('action', 'members.wallet_add')->count());
    }

    public function test_subtracting_cannot_take_a_wallet_below_zero()
    {
        $this->actingAs(Admin::factory()->super()->create(), 'admin');
        $member = User::factory()->create();
        $this->adjust($member, ['amount' => '100']);

        $this->adjust($member, ['direction' => 'subtract', 'amount' => '100.01'])->assertSessionHasErrors('amount');
        $this->adjust($member, ['direction' => 'subtract', 'amount' => '40'])->assertSessionHasNoErrors();

        $this->assertSame(6_000, app(Ledger::class)->balances($member)['main']);
    }

    public function test_bad_input_is_refused()
    {
        $this->actingAs(Admin::factory()->super()->create(), 'admin');
        $member = User::factory()->create();

        $this->adjust($member, ['amount' => '12.345'])->assertSessionHasErrors('amount');
        $this->adjust($member, ['amount' => '0'])->assertSessionHasErrors('amount');
        $this->adjust($member, ['reason' => ''])->assertSessionHasErrors('reason');
        $this->adjust($member, ['wallet' => 'pocket'])->assertSessionHasErrors('wallet');

        $this->assertSame(0, app(Ledger::class)->balances($member)['main']);
    }

    public function test_adjusting_needs_its_own_permission()
    {
        $this->actingAs(Admin::factory()->withPermissions(['members.view', 'members.edit'])->create(), 'admin');
        $member = User::factory()->create();

        $this->adjust($member)->assertForbidden();
        $this->get("/admin/members/{$member->id}/wallets/main")->assertOk();
    }

    public function test_a_member_sees_their_balances_and_statement_with_running_balances()
    {
        $member = User::factory()->create();
        $this->actingAs(Admin::factory()->super()->create(), 'admin');
        $this->adjust($member, ['amount' => '1000', 'reason' => 'Welcome credit']);
        $this->adjust($member, ['direction' => 'subtract', 'amount' => '250', 'reason' => 'Correction']);

        $this->app['auth']->forgetGuards();
        $this->actingAs($member, 'web')->get('/wallet')
            ->assertInertia(fn ($page) => $page
                ->component('wallet')
                ->where('balances.main', 75_000)
                ->where('entries.data.0.amount', -25_000)
                ->where('entries.data.0.balance', 75_000)
                ->where('entries.data.0.memo', 'Correction')
                ->where('entries.data.1.balance', 100_000));
    }

    public function test_a_member_cannot_see_someone_elses_wallet()
    {
        $other = User::factory()->create();

        $this->actingAs(User::factory()->create())->get('/wallet?wallet=main')->assertOk()->assertInertia(fn ($page) => $page->where('entries.total', 0));
        $this->get("/admin/members/{$other->id}/wallets/main")->assertRedirect('/admin/login');
    }
}
