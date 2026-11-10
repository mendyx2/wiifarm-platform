<?php

namespace Tests\Feature\Members;

use App\Models\User;
use Database\Seeders\CompanyAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_company_account_is_member_one_with_no_sponsor_and_is_created_once()
    {
        $this->seed(CompanyAccountSeeder::class);
        $this->seed(CompanyAccountSeeder::class);

        $company = User::company();
        $this->assertSame(1, $company->id);
        $this->assertNull($company->sponsor_id);
        $this->assertTrue($company->isActivated());
        $this->assertSame(1, User::count());
    }

    public function test_sign_up_is_refused_cleanly_when_the_company_account_is_missing()
    {
        $this->post('/register', [
            'first_name' => 'A', 'last_name' => 'B', 'username' => 'someone_new', 'email' => 'a@example.com',
            'dial_code' => '234', 'mobile' => '8012345678', 'country' => 'NG',
            'password' => 'password', 'password_confirmation' => 'password', 'terms' => true,
        ])->assertSessionHasErrors('sponsor');

        $this->assertGuest();
        $this->assertSame(0, User::count());
    }
}
