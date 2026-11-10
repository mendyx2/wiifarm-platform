<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_super_admin()
    {
        $this->artisan('wiifarm:create-admin')
            ->expectsQuestion('Full name', 'Chidi Eze')
            ->expectsQuestion('Username (letters, digits, underscores)', 'chidi_admin')
            ->expectsQuestion('Email address', 'Chidi@Example.com')
            ->expectsQuestion('Password (at least 12 characters)', 'a-long-password-1')
            ->expectsQuestion('Type the password again', 'a-long-password-1')
            ->assertSuccessful();

        $admin = Admin::where('username', 'chidi_admin')->firstOrFail();
        $this->assertTrue($admin->isSuper());
        $this->assertSame('chidi@example.com', $admin->email);
        $this->assertDatabaseHas('audit_logs', ['action' => 'admins.create', 'subject_id' => $admin->id, 'admin_id' => null]);
    }

    public function test_it_refuses_a_short_password()
    {
        $this->artisan('wiifarm:create-admin')
            ->expectsQuestion('Full name', 'Chidi Eze')
            ->expectsQuestion('Username (letters, digits, underscores)', 'chidi_admin')
            ->expectsQuestion('Email address', 'chidi@example.com')
            ->expectsQuestion('Password (at least 12 characters)', 'short')
            ->expectsQuestion('Type the password again', 'short')
            ->assertFailed();

        $this->assertSame(0, Admin::count());
    }
}
