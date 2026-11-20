<?php

namespace Tests\Feature\Payments;

use App\Models\Admin;
use App\Models\User;
use App\Services\Ledger\Ledger;
use App\Support\Settings;
use Illuminate\Support\Str;
use Tests\Feature\Network\NetworkTestCase;

/**
 * Balance transfer between members (specification section 6): from one main
 * balance to another, the admin's charge to the cooperative, once.
 */
class BalanceTransfersTest extends NetworkTestCase
{
    private Ledger $ledger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ledger = app(Ledger::class);
    }

    private function funded(string $username, int $kobo = 5_000_000): User
    {
        $member = $this->join($username);
        $this->ledger->transfer($this->ledger->systemAccount('adjustments'), $this->ledger->memberAccount($member, 'main'), $kobo, 'test', 'fund:'.$username);

        return $member;
    }

    private function send(array $data)
    {
        return $this->post('/wallet/send', $data + ['password' => 'password', 'form_id' => (string) Str::uuid()]);
    }

    public function test_money_moves_once_with_the_charge_to_the_cooperative()
    {
        Settings::set('transfers.fixed_charge', 10_000);     // ₦100
        Settings::set('transfers.percent_charge', '1.00');   // + 1%
        $ada = $this->funded('ada_sender');
        $bola = $this->join('bola_receiver');

        $this->actingAs($ada)->getJson('/wallet/recipient?username=bola_receiver')->assertOk()->assertJsonPath('username', 'bola_receiver');
        $form = (string) Str::uuid();
        $this->send(['username' => '@bola_receiver', 'amount' => '10,000', 'form_id' => $form])->assertSessionHasNoErrors();
        $this->send(['username' => 'bola_receiver', 'amount' => '10,000', 'form_id' => $form]);   // the same form again

        $this->assertSame(4_000_000, $this->ledger->balances($ada)['main']);
        $this->assertSame(980_000, $this->ledger->balances($bola)['main']);                          // ₦10,000 − ₦100 − ₦100
        $this->assertSame(20_000, $this->ledger->balance($this->ledger->cooperativeAccount('transfer_charges')));
        $this->assertDatabaseHas('message_log', ['event' => 'transfer.received', 'user_id' => $bola->id, 'subject' => 'You received ₦9,800.00 from @ada_sender']);
        $this->artisan('wiifarm:ledger-check')->assertSuccessful();
    }

    public function test_what_is_refused()
    {
        $ada = $this->funded('ada_sender', 100_000);
        $this->join('bola_receiver');
        $this->actingAs($ada);

        $this->send(['username' => 'bola_receiver', 'amount' => '500', 'password' => 'wrong'])->assertSessionHasErrors('password');
        $this->send(['username' => 'bola_receiver', 'amount' => '5,000'])->assertSessionHasErrors(['send' => 'There is not that much in your main balance.']);
        $this->send(['username' => 'ada_sender', 'amount' => '500'])->assertSessionHasErrors(['send' => 'You cannot send money to yourself.']);
        $this->send(['username' => 'nobody_here', 'amount' => '500'])->assertSessionHasErrors('send');
        $this->send(['username' => 'wiifarm', 'amount' => '500'])->assertSessionHasErrors('send');                     // the company account
        $this->send(['username' => 'bola_receiver', 'amount' => '50'])->assertSessionHasErrors('send');                // below ₦100
        $this->getJson('/wallet/recipient?username=nobody_here')->assertNotFound();

        $ada->forceFill(['withdrawals_blocked' => true])->save();
        $this->send(['username' => 'bola_receiver', 'amount' => '500'])->assertSessionHasErrors('send');
        $ada->forceFill(['withdrawals_blocked' => false])->save();
        Settings::set('transfers.enabled', false);
        $this->send(['username' => 'bola_receiver', 'amount' => '500'])->assertSessionHasErrors('send');
        $this->get('/wallet')->assertInertia(fn ($page) => $page->where('transfers', null));

        $this->assertSame(100_000, $this->ledger->balances($ada)['main']);
    }

    public function test_a_super_admin_sets_the_limits_and_charges()
    {
        $this->actingAs(Admin::factory()->withPermissions(['settings.view'])->create(), 'admin')
            ->put('/admin/settings/transfers', ['enabled' => true, 'min' => '200', 'max' => '500000', 'fixed_charge' => '50', 'percent_charge' => '0.5'])->assertForbidden();

        $this->actingAs(Admin::factory()->super()->create(), 'admin')
            ->put('/admin/settings/transfers', ['enabled' => true, 'min' => '200', 'max' => '500000', 'fixed_charge' => '50', 'percent_charge' => '0.5'])->assertSessionHasNoErrors();
        $this->assertSame([20_000, 50_000_000, 5_000, '0.50'], [Settings::get('transfers.min'), Settings::get('transfers.max'), Settings::get('transfers.fixed_charge'), Settings::get('transfers.percent_charge')]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.edit', 'note' => 'Balance transfers']);
    }
}
