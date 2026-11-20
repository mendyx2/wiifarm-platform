<?php

namespace Tests\Feature\Payments;

use App\Models\Admin;
use App\Models\Payments\DepositMethod;
use App\Models\Payments\ManualDeposit;
use App\Services\Ledger\Ledger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Feature\Network\NetworkTestCase;

/**
 * Deposits by bank transfer (specification section 6): the member uploads
 * proof, an admin approves (credited once) or refuses with a reason.
 */
class ManualDepositsTest extends NetworkTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        DepositMethod::where('name', 'Bank transfer')->update(['is_active' => true, 'instructions' => 'Wiifarm, First Bank 0123456789']);
    }

    private function send(array $data = [])
    {
        return $this->post('/deposit/manual', array_merge([
            'method_id' => DepositMethod::firstOrFail()->id, 'amount' => '25,000', 'reference' => 'TRF-001',
            'proof' => UploadedFile::fake()->image('receipt.jpg'), 'form_id' => (string) Str::uuid(),
        ], $data));
    }

    public function test_a_member_sends_proof_and_an_admin_approves_it_once()
    {
        $member = $this->join('payer_one');
        $this->actingAs($member)->get('/deposit')->assertInertia(fn ($page) => $page->where('methods.0.instructions', 'Wiifarm, First Bank 0123456789'));
        $this->send()->assertRedirect('/deposit')->assertSessionHasNoErrors();

        $deposit = ManualDeposit::sole();
        $this->assertSame([2_500_000, 'pending'], [$deposit->amount, $deposit->status]);
        Storage::disk('local')->assertExists($deposit->proof_path);
        $this->assertSame(0, app(Ledger::class)->balances($member)['main']);   // nothing until approved

        $this->actingAs(Admin::factory()->withPermissions(['members.view'])->create(), 'admin')->get("/admin/deposits/{$deposit->id}/proof")->assertForbidden();
        $viewer = Admin::factory()->withPermissions(['deposits.view'])->create();
        $this->actingAs($viewer, 'admin')->get("/admin/deposits/{$deposit->id}/proof")->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->actingAs($viewer, 'admin')->post("/admin/deposits/{$deposit->id}/approve")->assertForbidden();

        $admin = Admin::factory()->withPermissions(['deposits.view', 'deposits.decide'])->create();
        $this->actingAs($admin, 'admin')->get('/admin/deposits')->assertInertia(fn ($page) => $page->where('deposits.data.0.reference', 'TRF-001'));
        $this->post("/admin/deposits/{$deposit->id}/approve")->assertRedirect('/admin/deposits');
        $this->post("/admin/deposits/{$deposit->id}/approve")->assertSessionHasErrors('deposit');   // decided once

        $this->assertSame(2_500_000, app(Ledger::class)->balances($member)['main']);
        $this->assertSame('approved', $deposit->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'deposits.approve']);
        $this->assertDatabaseHas('message_log', ['event' => 'deposit.received', 'user_id' => $member->id]);
        $this->artisan('wiifarm:ledger-check')->assertSuccessful();
    }

    public function test_a_refused_deposit_adds_nothing_and_tells_the_member_why()
    {
        $member = $this->join('payer_two');
        $this->actingAs($member)->send();
        $deposit = ManualDeposit::sole();

        $admin = Admin::factory()->withPermissions(['deposits.view', 'deposits.decide'])->create();
        $this->actingAs($admin, 'admin')->post("/admin/deposits/{$deposit->id}/reject", ['reason' => ''])->assertSessionHasErrors('reason');
        $this->post("/admin/deposits/{$deposit->id}/reject", ['reason' => 'No payment with this reference arrived.'])->assertRedirect('/admin/deposits');

        $this->assertSame(0, app(Ledger::class)->balances($member)['main']);
        $this->assertDatabaseHas('message_log', ['event' => 'deposit.rejected', 'subject' => 'Your deposit of ₦25,000.00 was not approved']);
        $this->actingAs($member)->get('/deposit')->assertInertia(fn ($page) => $page
            ->where('manual.0.status', 'rejected')->where('manual.0.reason', 'No payment with this reference arrived.'));
    }

    public function test_limits_proof_and_the_method_s_switch_are_checked()
    {
        $this->actingAs($this->join('payer_three'));
        $this->send(['amount' => '500'])->assertSessionHasErrors('amount');                                  // below ₦1,000
        $this->send(['proof' => UploadedFile::fake()->create('virus.exe', 10)])->assertSessionHasErrors('proof');
        $form = (string) Str::uuid();
        $this->send(['form_id' => $form]);
        $this->send(['form_id' => $form]);                                                                     // sent twice
        $this->assertSame(1, ManualDeposit::count());

        DepositMethod::query()->update(['is_active' => false]);
        $this->send()->assertSessionHasErrors('amount');
        $this->get('/deposit')->assertInertia(fn ($page) => $page->where('methods', []));
    }

    public function test_an_admin_sets_up_the_bank_details()
    {
        $admin = Admin::factory()->withPermissions(['deposits.view', 'deposits.methods'])->create();
        $method = DepositMethod::firstOrFail();
        $this->actingAs($admin, 'admin')->put("/admin/deposits/methods/{$method->id}", [
            'name' => 'Bank transfer', 'instructions' => 'Zenith Bank, 1012345678, Wiifarm Cooperative', 'min_amount' => '2000', 'max_amount' => '1000000', 'is_active' => true,
        ])->assertSessionHasNoErrors();

        $this->assertSame([200_000, true], [$method->fresh()->min_amount, $method->fresh()->is_active]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'deposits.method_edit']);
        $this->actingAs(Admin::factory()->withPermissions(['deposits.view'])->create(), 'admin')->get('/admin/deposits/methods')->assertForbidden();
    }
}
