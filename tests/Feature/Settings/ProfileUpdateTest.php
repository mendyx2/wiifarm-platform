<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/settings/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/settings/profile', [
                'first_name' => 'Test',
                'last_name' => 'User',
                'email' => 'test@example.com',
                'dial_code' => '234',
                'mobile' => '8099998888',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/settings/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('8099998888', $user->mobile);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/settings/profile', [
                'first_name' => 'Test',
                'last_name' => 'User',
                'email' => $user->email,
                'dial_code' => $user->dial_code,
                'mobile' => $user->mobile,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/settings/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_the_username_cannot_be_changed()
    {
        $user = User::factory()->create(['username' => 'original_name']);

        $this->actingAs($user)->patch('/settings/profile', [
            'first_name' => 'Test', 'last_name' => 'User', 'email' => $user->email,
            'dial_code' => '234', 'mobile' => '8099998888', 'username' => 'new_name_here',
        ]);

        $this->assertSame('original_name', $user->refresh()->username);
    }

    public function test_members_cannot_delete_their_own_account()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->delete('/settings/profile', ['password' => 'password'])->assertStatus(405);

        $this->assertNotNull($user->fresh());
    }
}
