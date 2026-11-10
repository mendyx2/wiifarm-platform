<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\CompanyAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sign-up (specification section 2).
 */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CompanyAccountSeeder::class);
    }

    /** @return array<string, mixed> */
    private function form(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Amaka',
            'last_name' => 'Okafor',
            'username' => 'amaka_okafor',
            'email' => 'amaka@example.com',
            'dial_code' => '234',
            'mobile' => '8012345678',
            'country' => 'NG',
            'sponsor' => '',
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => true,
        ], $overrides);
    }

    public function test_registration_screen_can_be_rendered()
    {
        $this->get('/register')->assertStatus(200);
    }

    public function test_new_users_can_register()
    {
        $response = $this->post('/register', $this->form());

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));

        $user = User::where('username', 'amaka_okafor')->firstOrFail();
        $this->assertSame('Amaka Okafor', $user->name);
        $this->assertSame('NG', $user->country);
        $this->assertFalse($user->isActivated(), 'A new account stays pending until its package is paid.');
    }

    public function test_a_blank_sponsor_means_the_company_account()
    {
        $this->post('/register', $this->form());

        $this->assertSame(User::company()->id, User::where('username', 'amaka_okafor')->value('sponsor_id'));
    }

    public function test_a_named_sponsor_is_recorded()
    {
        $sponsor = User::factory()->create(['username' => 'tunde_bakare']);

        $this->post('/register', $this->form(['sponsor' => 'tunde_bakare']));

        $this->assertSame($sponsor->id, User::where('username', 'amaka_okafor')->value('sponsor_id'));
    }

    public function test_an_unknown_or_banned_sponsor_is_refused()
    {
        $this->post('/register', $this->form(['sponsor' => 'nobody_here']))->assertSessionHasErrors('sponsor');

        User::factory()->banned()->create(['username' => 'banned_one']);
        $this->post('/register', $this->form(['sponsor' => 'banned_one']))->assertSessionHasErrors('sponsor');

        $this->assertGuest();
    }

    public function test_username_rules()
    {
        foreach (['short', 'has space', 'dash-name', str_repeat('a', 41)] as $bad) {
            $this->post('/register', $this->form(['username' => $bad]))->assertSessionHasErrors('username');
        }

        User::factory()->create(['username' => 'taken_name']);
        $this->post('/register', $this->form(['username' => 'taken_name']))->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_a_local_leading_zero_is_dropped_from_the_mobile_number()
    {
        $this->post('/register', $this->form(['mobile' => '08012345678']));

        $this->assertSame('8012345678', User::where('username', 'amaka_okafor')->value('mobile'));
    }

    public function test_the_terms_must_be_accepted()
    {
        $this->post('/register', $this->form(['terms' => false]))->assertSessionHasErrors('terms');
        $this->assertGuest();
    }

    public function test_status_and_sponsor_cannot_be_set_from_the_form()
    {
        $other = User::factory()->create();

        $this->post('/register', $this->form(['status' => 'banned', 'sponsor_id' => $other->id, 'activated_at' => now()]));

        $user = User::where('username', 'amaka_okafor')->firstOrFail();
        $this->assertSame('active', $user->status);
        $this->assertSame(User::company()->id, $user->sponsor_id);
        $this->assertNull($user->activated_at);
    }
}
