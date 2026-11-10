<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered()
    {
        $this->get('/login')->assertStatus(200);
    }

    public function test_members_can_sign_in_with_their_email()
    {
        $user = User::factory()->create();

        $response = $this->post('/login', ['login' => $user->email, 'password' => 'password']);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_members_can_sign_in_with_their_username()
    {
        $user = User::factory()->create(['username' => 'amaka_okafor']);

        $this->post('/login', ['login' => 'amaka_okafor', 'password' => 'password']);

        $this->assertAuthenticatedAs($user);
    }

    public function test_users_can_not_authenticate_with_invalid_password()
    {
        $user = User::factory()->create();

        $this->post('/login', ['login' => $user->email, 'password' => 'wrong-password']);

        $this->assertGuest();
    }

    public function test_banned_members_cannot_sign_in_and_see_why()
    {
        $user = User::factory()->banned('Fake documents')->create();

        $this->post('/login', ['login' => $user->username, 'password' => 'password'])
            ->assertSessionHasErrors(['login' => 'This account has been banned: Fake documents Contact Wiifarm support if you think this is a mistake.']);

        $this->assertGuest();
    }

    public function test_a_member_banned_while_signed_in_is_signed_out()
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/dashboard')->assertOk();

        $user->forceFill(['status' => User::STATUS_BANNED, 'ban_reason' => 'Fraud'])->save();

        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_users_can_logout()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
