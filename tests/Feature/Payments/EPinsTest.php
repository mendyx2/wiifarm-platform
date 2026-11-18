<?php

namespace Tests\Feature\Payments;

use App\Models\Admin;
use App\Models\Payments\EPin;
use App\Models\User;
use App\Services\Ledger\Ledger;
use Illuminate\Support\Str;
use Tests\Feature\Network\NetworkTestCase;

/**
 * E-pins (specification section 6): bought from the VISA wallet or created by
 * an admin; each pays one registration, once.
 */
class EPinsTest extends NetworkTestCase
{
    private Ledger $ledger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ledger = app(Ledger::class);
    }

    private function fundVisa(User $member, int $kobo): void
    {
        $this->ledger->transfer($this->ledger->systemAccount('adjustments'), $this->ledger->memberAccount($member, 'visa'), $kobo, 'test.fund', 'fund:visa:'.$member->id.':'.Str::uuid());
    }

    private function wallet(User $member, string $wallet): int
    {
        return $this->ledger->balances($member)[$wallet];
    }

    private function buy(User $member, string $package, int $quantity, ?string $formId = null)
    {
        return $this->actingAs($member)->post('/epins', ['package_id' => $this->package($package)->id, 'quantity' => $quantity, 'form_id' => $formId ?? (string) Str::uuid()]);
    }

    private function pending(string $username = 'pin_user', ?User $sponsor = null): User
    {
        return User::factory()->create(['username' => $username, 'sponsor_id' => ($sponsor ?? User::company())->id]);
    }

    public function test_a_member_buys_pins_from_the_visa_wallet_once_per_form()
    {
        $member = $this->join('pin_seller');
        $this->fundVisa($member, 10_000_000);
        $form = (string) Str::uuid();

        $this->buy($member, 'Starter', 3, $form)->assertSessionHasNoErrors();
        $this->buy($member, 'Starter', 3, $form)->assertSessionHasNoErrors();   // sent twice

        $this->assertSame(3, EPin::where('created_by_user_id', $member->id)->count());
        $this->assertSame(10_000_000 - 9_000_000, $this->wallet($member, 'visa'));
        $this->assertSame(9_000_000, $this->ledger->balance($this->ledger->systemAccount('epins.outstanding')));
        $this->get('/epins')->assertInertia(fn ($page) => $page->has('pins.data', 3)->where('pins.data.0.code', fn ($c) => preg_match('/^[A-Z2-9]{4}-[A-Z2-9]{4}-[A-Z2-9]{4}$/', $c) === 1));
        $this->assertStringNotContainsString(EPin::first()->code(), (string) EPin::first()->code_encrypted);   // stored encrypted

        $this->buy($member, 'Mega', 1)->assertSessionHasErrors(['quantity' => 'Your VISA wallet does not have enough for this.']);
        $this->buy($member, 'Starter', 51)->assertSessionHasErrors('quantity');
        $this->buy($this->pending('not_paid_yet'), 'Starter', 1)->assertSessionHasErrors(['quantity' => 'Only members with a paid package can buy e-pins.']);
    }

    public function test_a_person_who_signed_up_pays_their_package_with_a_pin_once()
    {
        $seller = $this->join('pin_seller');
        $this->fundVisa($seller, 3_000_000);
        $this->buy($seller, 'Starter', 1);
        $pin = EPin::sole();
        $code = strtolower(str_replace('-', ' ', $pin->code()));   // typed loosely
        $person = $this->pending();

        $this->actingAs($person)->post('/package/epin', ['code' => 'WRONG-CODE-0000', 'package_id' => $this->package('Starter')->id])
            ->assertSessionHasErrors(['code' => 'That e-pin is not right. Check it and try again.']);
        $this->post('/package/epin', ['code' => $code, 'package_id' => $this->package('Starter')->id])->assertSessionHasNoErrors();

        $person->refresh();
        $this->assertSame('Starter', $person->package->name);
        $pin->refresh();
        $this->assertSame([EPin::USED, $person->id], [$pin->status, $pin->used_by_user_id]);
        $this->assertSame(0, $this->ledger->balance($this->ledger->systemAccount('epins.outstanding')));
        $this->artisan('wiifarm:ledger-check')->assertSuccessful();

        $other = $this->pending('second_try');
        $this->actingAs($other)->post('/package/epin', ['code' => $code, 'package_id' => $this->package('Starter')->id])
            ->assertSessionHasErrors(['code' => 'That e-pin has already been used.']);
    }

    public function test_a_pin_pays_a_cheaper_package_with_the_rest_to_the_main_balance_but_never_a_dearer_one()
    {
        $seller = $this->join('pin_seller');
        $this->fundVisa($seller, 3_000_000);
        $this->buy($seller, 'Starter', 1);
        $code = EPin::sole()->code();
        $person = $this->pending();

        $this->actingAs($person)->post('/package/epin', ['code' => $code, 'package_id' => $this->package('Builder')->id])
            ->assertSessionHasErrors(['code' => 'This e-pin is worth ₦30,000, less than the Builder package (₦60,000).']);
        $this->post('/package/epin', ['code' => $code, 'package_id' => $this->package('Pre Starter')->id])->assertSessionHasNoErrors();

        $this->assertSame('Pre Starter', $person->fresh()->package->name);
        $this->assertSame(1_500_000, $this->wallet($person, 'main'));   // ₦30,000 − ₦15,000
        $this->artisan('wiifarm:ledger-check')->assertSuccessful();
    }

    public function test_a_pin_that_cannot_place_its_user_stays_unused()
    {
        $seller = $this->join('pin_seller');
        $this->fundVisa($seller, 1_500_000);
        $this->buy($seller, 'Pre Starter', 1);
        $pin = EPin::sole();
        $person = $this->pending('recruit', $this->pending('unpaid_sponsor'));

        $this->actingAs($person)->post('/package/epin', ['code' => $pin->code(), 'package_id' => $this->package('Pre Starter')->id])->assertSessionHasErrors('code');

        $this->assertSame(EPin::ACTIVE, $pin->fresh()->status);
        $this->assertNull($person->fresh()->activated_at);
        $this->assertSame(1_500_000, $this->ledger->balance($this->ledger->systemAccount('epins.outstanding')));
    }

    public function test_admins_create_see_and_cancel_pins_with_permission()
    {
        $seller = $this->join('pin_seller');
        $this->fundVisa($seller, 1_500_000);
        $this->buy($seller, 'Pre Starter', 1);
        $bought = EPin::sole();

        $viewer = Admin::factory()->withPermissions(['epins.view'])->create();
        $this->actingAs($viewer, 'admin');
        $this->get('/admin/epins')->assertInertia(fn ($page) => $page->where('pins.data.0.code', null)->where('pins.data.0.created_by', '@pin_seller'));
        $this->post('/admin/epins', ['package_id' => $this->package('Starter')->id, 'quantity' => 2, 'reason' => 'x', 'form_id' => (string) Str::uuid()])->assertForbidden();

        $manager = Admin::factory()->withPermissions(['epins.view', 'epins.manage'])->create();
        $this->actingAs($manager, 'admin');
        $this->post('/admin/epins', ['package_id' => $this->package('Starter')->id, 'quantity' => 2, 'reason' => '', 'form_id' => (string) Str::uuid()])->assertSessionHasErrors('reason');
        $this->post('/admin/epins', ['package_id' => $this->package('Starter')->id, 'quantity' => 2, 'reason' => 'Launch prizes', 'form_id' => (string) Str::uuid()])->assertSessionHasNoErrors();
        $this->assertSame(2, EPin::whereNotNull('created_by_admin_id')->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'epins.create']);
        $this->get('/admin/epins?status=active')->assertInertia(fn ($page) => $page->where('pins.data.0.code', fn ($c) => is_string($c)));

        // Cancelling a bought pin returns its value to the buyer's VISA wallet.
        $this->post("/admin/epins/{$bought->id}/cancel", ['reason' => 'Bought by mistake'])->assertSessionHasNoErrors();
        $this->assertSame([EPin::CANCELLED, 1_500_000], [$bought->fresh()->status, $this->wallet($seller, 'visa')]);
        $this->post("/admin/epins/{$bought->id}/cancel", ['reason' => 'again'])->assertSessionHasErrors(['reason' => 'Only an unused e-pin can be cancelled.']);
        $this->artisan('wiifarm:ledger-check')->assertSuccessful();
    }
}
