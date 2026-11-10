<?php

namespace Tests\Feature\Auth;

use App\Contracts\SmsSender;
use App\Models\User;
use App\Support\Settings;
use Database\Seeders\CompanyAccountSeeder;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * The email and phone verification switches (specification section 2).
 */
class VerificationSwitchesTest extends TestCase
{
    use RefreshDatabase;

    /** Captures the last SMS instead of sending it. */
    private object $sms;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sms = new class implements SmsSender
        {
            public array $sent = [];

            public function send(string $to, string $message): void
            {
                $this->sent[] = [$to, $message];
            }
        };
        $this->app->instance(SmsSender::class, $this->sms);
    }

    private function lastCode(): string
    {
        preg_match('/\b(\d{6})\b/', end($this->sms->sent)[1], $m);

        return $m[1];
    }

    public function test_with_both_switches_off_unverified_members_use_the_dashboard()
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get('/dashboard')->assertOk();
    }

    public function test_email_switch_on_sends_members_to_confirm_their_email_but_leaves_settings_open()
    {
        Settings::set('verification.email_required', true);
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('verification.notice'));
        $this->actingAs($user)->get('/settings/profile')->assertOk();
    }

    public function test_email_switch_on_sends_the_confirmation_email_at_sign_up()
    {
        Notification::fake();
        $this->seed(CompanyAccountSeeder::class);
        Settings::set('verification.email_required', true);

        $this->post('/register', [
            'first_name' => 'Amaka', 'last_name' => 'Okafor', 'username' => 'amaka_okafor', 'email' => 'amaka@example.com',
            'dial_code' => '234', 'mobile' => '8012345678', 'country' => 'NG',
            'password' => 'password', 'password_confirmation' => 'password', 'terms' => true,
        ]);

        Notification::assertSentTo(User::where('username', 'amaka_okafor')->first(), VerifyEmail::class);
    }

    public function test_phone_switch_on_requires_a_correct_code()
    {
        Settings::set('verification.mobile_required', true);
        $user = User::factory()->create(['dial_code' => '234', 'mobile' => '8012345678']);

        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('mobile.verify'));

        $this->post('/verify-mobile/send')->assertSessionHas('status', 'code-sent');
        $this->assertSame('2348012345678', end($this->sms->sent)[0]);

        $this->post('/verify-mobile', ['code' => '000000'])->assertSessionHasErrors('code');
        $this->post('/verify-mobile', ['code' => $this->lastCode()])->assertRedirect(route('dashboard'));

        $this->assertNotNull($user->fresh()->mobile_verified_at);
        $this->get('/dashboard')->assertOk();
    }

    public function test_a_code_expires_and_too_many_wrong_tries_void_it()
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/verify-mobile/send');
        $code = $this->lastCode();

        $this->travel(11)->minutes();
        $this->post('/verify-mobile', ['code' => $code])->assertSessionHasErrors(['code' => 'That code has expired. Request a new one.']);

        $this->travelBack();
        $this->post('/verify-mobile/send');
        $code = $this->lastCode();
        $wrong = $code === '111111' ? '222222' : '111111';
        for ($i = 0; $i < 5; $i++) {
            $this->post('/verify-mobile', ['code' => $wrong]);
        }
        $this->post('/verify-mobile', ['code' => $code])->assertSessionHasErrors(['code' => 'Too many wrong codes. Request a new one.']);
        $this->assertNull($user->fresh()->mobile_verified_at);
    }

    public function test_changing_the_mobile_number_clears_its_verification()
    {
        $user = User::factory()->create(['mobile_verified_at' => now()]);

        $this->actingAs($user)->patch('/settings/profile', [
            'first_name' => $user->first_name, 'last_name' => $user->last_name, 'email' => $user->email,
            'dial_code' => '234', 'mobile' => '8098765432',
        ]);

        $this->assertNull($user->fresh()->mobile_verified_at);
    }
}
