<?php

namespace Tests\Feature\Network;

use App\Models\User;
use App\Services\Ledger\Ledger;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * "Add Distributor": a member registers someone and pays from their VISA
 * wallet (specification sections 2 and 5.3).
 */
class KeyInTest extends NetworkTestCase
{
    private function fundVisa(User $member, int $kobo): void
    {
        $l = app(Ledger::class);
        $l->transfer($l->systemAccount('adjustments'), $l->memberAccount($member, 'visa'), $kobo, 'test.fund', 'fund:visa:'.$member->id.':'.Str::uuid());
    }

    private function wallet(User $member, string $wallet): int
    {
        return app(Ledger::class)->balances($member)[$wallet];
    }

    /** @param array<string, mixed> $over */
    private function form(User $by, array $over = []): array
    {
        return $over + [
            'first_name' => 'Tunde', 'last_name' => 'Bello', 'username' => 'tunde_b', 'email' => 'tunde@example.com',
            'dial_code' => '234', 'mobile' => '8031234567', 'country' => 'NG', 'state' => 'Lagos', 'city' => 'Ikeja', 'address' => '12 Allen Avenue',
            'sponsor' => $by->username, 'parent' => '', 'leg' => '', 'package_id' => $this->package('Starter')->id,
            'password' => 'a-good-password-1', 'password_confirmation' => 'a-good-password-1', 'terms' => true, 'form_id' => (string) Str::uuid(),
        ];
    }

    public function test_a_member_keys_someone_in_and_pays_from_the_visa_wallet()
    {
        $me = $this->join('key_in_er');
        $this->fundVisa($me, 5_000_000);

        $form = $this->form($me);
        $this->actingAs($me)->post('/team/add', $form)->assertRedirect('/team')->assertSessionHasNoErrors();
        $this->post('/team/add', $form)->assertRedirect('/team');   // sent twice: done once

        $new = User::where('username', 'tunde_b')->sole();
        $this->assertSame(['Starter', $me->id, 'Lagos', 'Ikeja'], [$new->package->name, $new->sponsor_id, $new->state, $new->city]);
        $this->assertNotNull($new->activated_at);
        $this->assertSame(5_000_000 - 3_000_000, $this->wallet($me, 'visa'));
        // 5.3: 2% of ₦18,000 PV value = ₦360 key-in; 5.1: and as sponsor, 25% = ₦4,500 direct.
        $this->assertSame([36_000, 450_000], [$this->wallet($me, 'bonus.keyin'), $this->wallet($me, 'bonus.direct')]);
        $this->assertTrue(Hash::check('a-good-password-1', $new->password));   // they sign in with the password chosen for them
        $this->artisan('wiifarm:ledger-check')->assertSuccessful();
    }

    public function test_nothing_is_created_when_the_visa_wallet_is_short_or_the_form_is_incomplete()
    {
        $me = $this->join('key_in_er');
        $this->fundVisa($me, 1_000_000);
        $this->actingAs($me);

        $this->post('/team/add', $this->form($me))->assertSessionHasErrors(['package_id' => 'Your VISA wallet does not have the ₦30,000 this package costs.']);
        $this->post('/team/add', $this->form($me, ['state' => '', 'address' => '', 'sponsor' => '', 'terms' => false]))->assertSessionHasErrors(['state', 'address', 'sponsor', 'terms']);
        $this->post('/team/add', $this->form($me, ['package_id' => $this->package('Pre Starter')->id, 'parent' => 'wiifarm']))->assertSessionHasErrors('parent');

        $this->assertFalse(User::where('username', 'tunde_b')->exists());
        $this->assertSame(1_000_000, $this->wallet($me, 'visa'));
    }

    public function test_a_member_without_a_paid_package_cannot_key_in()
    {
        $me = User::factory()->create(['username' => 'not_paid', 'sponsor_id' => User::company()->id]);
        $this->fundVisa($me, 5_000_000);

        $this->actingAs($me)->get('/team/add')->assertInertia(fn ($page) => $page->where('canKeyIn', false));
        $this->post('/team/add', $this->form($me, ['sponsor' => 'wiifarm']))->assertSessionHasErrors(['sponsor' => 'Only members with a paid package can add distributors.']);
        $this->assertFalse(User::where('username', 'tunde_b')->exists());
    }
}
