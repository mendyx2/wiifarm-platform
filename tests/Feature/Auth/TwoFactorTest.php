<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\Members\TwoFactor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * Two-factor sign-in (specification section 2: optional per member).
 */
class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    private function appCode(User $user): string
    {
        return (new Google2FA)->getCurrentOtp((string) app(TwoFactor::class)->secret($user->fresh()));
    }

    /** Turns two-factor on and returns the backup codes. @return list<string> */
    private function turnOn(User $user): array
    {
        $this->actingAs($user)->post('/settings/two-factor', ['password' => 'password']);
        $this->post('/settings/two-factor/confirm', ['code' => $this->appCode($user)]);

        return session('two_factor.new_codes');
    }

    public function test_turning_it_on_needs_the_password_and_a_correct_first_code()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/settings/two-factor', ['password' => 'wrong'])->assertSessionHasErrors('password');
        $this->assertNull($user->fresh()->two_factor_secret);

        $this->post('/settings/two-factor', ['password' => 'password'])->assertRedirect(route('two-factor.edit'));
        $this->assertNotNull($user->fresh()->two_factor_secret);
        $this->assertNull($user->fresh()->two_factor_confirmed_at, 'Not in force until confirmed.');

        $this->post('/settings/two-factor/confirm', ['code' => '000000'])->assertSessionHasErrors('code');
        $this->post('/settings/two-factor/confirm', ['code' => $this->appCode($user)])->assertSessionHas('two_factor.new_codes');

        $this->assertNotNull($user->fresh()->two_factor_confirmed_at);
        $this->assertCount(8, session('two_factor.new_codes'));
    }

    public function test_the_secret_is_stored_encrypted_and_never_sent_to_the_browser()
    {
        $user = User::factory()->create();
        $this->turnOn($user);

        $raw = \DB::table('users')->where('id', $user->id)->value('two_factor_secret');
        $this->assertNotSame(app(TwoFactor::class)->secret($user->fresh()), $raw);
        $this->assertArrayNotHasKey('two_factor_secret', $user->fresh()->toArray());
    }

    public function test_sign_in_asks_for_a_code_and_a_wrong_code_does_not_sign_in()
    {
        $user = User::factory()->create();
        $this->turnOn($user);
        auth()->logout();

        $this->post('/login', ['login' => $user->email, 'password' => 'password'])->assertRedirect(route('two-factor.challenge'));
        $this->assertGuest();

        $this->post('/two-factor-challenge', ['code' => '000000'])->assertSessionHasErrors('code');
        $this->assertGuest();

        $this->post('/two-factor-challenge', ['code' => $this->appCode($user)])->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($user);
    }

    public function test_a_backup_code_works_once()
    {
        $user = User::factory()->create();
        $codes = $this->turnOn($user);
        auth()->logout();

        $this->post('/login', ['login' => $user->email, 'password' => 'password']);
        $this->post('/two-factor-challenge', ['code' => strtolower($codes[0])]);
        $this->assertAuthenticatedAs($user);
        $this->assertSame(7, app(TwoFactor::class)->backupCodesLeft($user->fresh()));

        $this->post('/logout');
        $this->post('/login', ['login' => $user->email, 'password' => 'password']);
        $this->post('/two-factor-challenge', ['code' => $codes[0]])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_the_challenge_page_cannot_be_reached_without_a_password_first()
    {
        $this->get('/two-factor-challenge')->assertRedirect(route('login'));
        $this->post('/two-factor-challenge', ['code' => '123456'])->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_turning_it_off_needs_the_password()
    {
        $user = User::factory()->create();
        $this->turnOn($user);

        $this->delete('/settings/two-factor', ['password' => 'wrong'])->assertSessionHasErrors('password');
        $this->assertNotNull($user->fresh()->two_factor_confirmed_at);

        $this->delete('/settings/two-factor', ['password' => 'password']);
        $this->assertNull($user->fresh()->two_factor_confirmed_at);
        $this->assertNull($user->fresh()->two_factor_secret);
    }

    public function test_members_without_two_factor_sign_in_as_before()
    {
        $user = User::factory()->create();

        $this->post('/login', ['login' => $user->email, 'password' => 'password'])->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($user);
    }
}
