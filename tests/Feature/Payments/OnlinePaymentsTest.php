<?php

namespace Tests\Feature\Payments;

use App\Models\Admin;
use App\Models\PackageChange;
use App\Models\Payments\OnlinePayment;
use App\Models\User;
use App\Services\Ledger\Ledger;
use App\Services\Payments\OnlinePayments;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Network\NetworkTestCase;

/**
 * Paying online through Paystack (specification sections 2, 4 and 6).
 * Paystack's answers are faked: nothing here talks to the real Paystack.
 */
class OnlinePaymentsTest extends NetworkTestCase
{
    private const SECRET = 'sk_test_wiifarm';

    /** What the fake Paystack answers about a payment, per reference ('*' for any). @var array<string, string> */
    private array $answers = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.paystack.secret_key' => self::SECRET, 'services.paystack.base_url' => 'https://paystack.test']);
    }

    /** Paystack's replies: a payment page, and $verify as the answer to "what happened?". */
    private function paystack(string $status = 'success', ?int $amount = null): void
    {
        $first = $this->answers === [];
        $this->answers['*'] = $status;
        if (! $first) {
            return; // already faked: only the answer changes
        }
        Http::fake([
            'paystack.test/transaction/initialize' => Http::response(['status' => true, 'data' => ['authorization_url' => 'https://checkout.paystack.test/abc']]),
            'paystack.test/transaction/verify/*' => function (Request $request) use ($amount) {
                $reference = basename($request->url());
                $payment = OnlinePayment::where('reference', $reference)->first();

                return Http::response(['status' => true, 'data' => [
                    'status' => $this->answers[$reference] ?? $this->answers['*'], 'reference' => $reference, 'amount' => $amount ?? $payment?->amount, 'currency' => 'NGN', 'channel' => 'card',
                ]]);
            },
        ]);
    }

    private function pending(string $username = 'new_person', ?User $sponsor = null): User
    {
        return User::factory()->create(['username' => $username, 'sponsor_id' => ($sponsor ?? User::company())->id]);
    }

    private function webhook(OnlinePayment $payment, ?int $amount = null, ?string $signature = null)
    {
        $body = json_encode(['event' => 'charge.success', 'data' => [
            'status' => 'success', 'reference' => $payment->reference, 'amount' => $amount ?? $payment->amount, 'currency' => 'NGN', 'channel' => 'bank',
        ]]);

        return $this->call('POST', '/webhooks/paystack', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_PAYSTACK_SIGNATURE' => $signature ?? hash_hmac('sha512', $body, self::SECRET),
        ], $body);
    }

    private function main(User $member): int
    {
        return app(Ledger::class)->balances($member)['main'];
    }

    public function test_a_new_member_pays_their_package_and_becomes_a_member_when_paystack_confirms()
    {
        $this->paystack();
        $person = $this->pending();

        $this->actingAs($person)->post('/package', ['package_id' => $this->package('Starter')->id])
            ->assertRedirect('https://checkout.paystack.test/abc');
        $payment = OnlinePayment::sole();
        $this->assertSame([OnlinePayment::PACKAGE, 3_000_000, OnlinePayment::PENDING], [$payment->purpose, $payment->amount, $payment->status]);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/initialize') && $r['amount'] === 3_000_000 && $r['reference'] === $payment->reference);
        $this->assertNull($person->fresh()->activated_at);   // nothing happens before Paystack confirms

        // Back from Paystack: our server asks Paystack, and the member is placed.
        $this->get('/payments/return?reference='.$payment->reference)->assertRedirect('/package')->assertSessionHas('status', fn ($s) => str_contains($s, 'now a member on Starter'));
        $person->refresh();
        $this->assertNotNull($person->activated_at);
        $this->assertSame('paystack:'.$payment->reference, PackageChange::where('user_id', $person->id)->sole()->payment_key);

        // Paystack's own message arriving afterwards changes nothing.
        $this->webhook($payment)->assertOk();
        $this->assertSame(1, PackageChange::where('user_id', $person->id)->count());
        $this->artisan('wiifarm:ledger-check')->assertSuccessful();
    }

    public function test_a_browser_coming_back_is_not_trusted_and_the_signed_webhook_decides()
    {
        $this->paystack('abandoned');
        $person = $this->pending();
        $this->actingAs($person)->post('/package', ['package_id' => $this->package('Pre Starter')->id]);
        $payment = OnlinePayment::sole();

        $this->get('/payments/return?reference='.$payment->reference)->assertSessionHas('status', fn ($s) => str_contains($s, 'not completed'));
        $this->assertSame(OnlinePayment::FAILED, $payment->fresh()->status);
        $this->assertNull($person->fresh()->activated_at);

        // A forged message is refused; Paystack's signed one is acted on, even after "failed".
        $this->webhook($payment, signature: 'forged')->assertStatus(401);
        $this->assertNull($person->fresh()->activated_at);
        $this->webhook($payment)->assertOk();
        $this->assertNotNull($person->fresh()->activated_at);
        $this->assertSame(OnlinePayment::PAID, $payment->fresh()->status);
    }

    public function test_the_wrong_amount_is_never_credited()
    {
        $this->paystack();
        $member = $this->join('depositor');
        $this->actingAs($member)->post('/deposit', ['amount' => '10,000']);
        $payment = OnlinePayment::sole();

        $this->webhook($payment, amount: 100_000)->assertOk();      // ₦1,000 instead of ₦10,000

        $this->assertSame(OnlinePayment::MISMATCH, $payment->fresh()->status);
        $this->assertSame(0, $this->main($member));
        $this->webhook($payment)->assertOk();                        // settled: an admin must look
        $this->assertSame(0, $this->main($member));
    }

    public function test_a_deposit_is_credited_to_the_main_balance_once()
    {
        $this->paystack();
        $member = $this->join('depositor');
        $this->actingAs($member);

        $this->post('/deposit', ['amount' => '500'])->assertSessionHasErrors('amount');              // below ₦1,000
        $this->post('/deposit', ['amount' => 'lots'])->assertSessionHasErrors('amount');
        $this->post('/deposit', ['amount' => '25,000'])->assertRedirect('https://checkout.paystack.test/abc');
        $payment = OnlinePayment::sole();

        $this->webhook($payment)->assertOk();
        $this->webhook($payment)->assertOk();
        $this->get('/payments/return?reference='.$payment->reference)->assertRedirect('/deposit');

        $this->assertSame(2_500_000, $this->main($member));
        $this->assertSame(1, DB::table('message_log')->where(['event' => 'deposit.received', 'user_id' => $member->id, 'subject' => 'Your deposit of ₦25,000.00 is in'])->count());   // told once
        $this->get('/deposit')->assertInertia(fn ($page) => $page->where('payments.0.status', 'paid')->where('online', true));
    }

    public function test_an_upgrade_pays_the_difference_and_a_stale_one_goes_to_the_main_balance()
    {
        $this->paystack();
        $member = $this->join('upgrader', package: 'Starter');
        $this->actingAs($member);

        $this->post('/package', ['package_id' => $this->package('Pre Starter')->id])->assertSessionHasErrors('package_id');   // lower
        $this->post('/package', ['package_id' => $this->package('Builder')->id]);
        $toBuilder = OnlinePayment::sole();
        $this->assertSame([OnlinePayment::UPGRADE, 3_000_000], [$toBuilder->purpose, $toBuilder->amount]);    // ₦60,000 − ₦30,000
        $this->post('/package', ['package_id' => $this->package('Mega')->id]);
        $toMega = OnlinePayment::latest('id')->first();

        $this->webhook($toBuilder)->assertOk();
        $this->assertSame('Builder', $member->fresh()->package->name);

        // The Mega payment was worked out from Starter; the member is now on Builder, so it is not applied.
        $this->webhook($toMega)->assertOk();
        $this->assertSame('Builder', $member->fresh()->package->name);
        $this->assertSame($toMega->amount, $this->main($member));
        $this->assertStringContainsString('main balance', $toMega->fresh()->note);
        $this->artisan('wiifarm:ledger-check')->assertSuccessful();
    }

    public function test_nobody_pays_who_cannot_be_placed_or_is_banned()
    {
        $this->paystack();
        $unpaidSponsor = $this->pending('unpaid_sponsor');
        $person = $this->pending('their_recruit', $unpaidSponsor);

        $this->actingAs($person)->post('/package', ['package_id' => $this->package('Starter')->id])
            ->assertSessionHasErrors(['package_id' => 'Your sponsor @unpaid_sponsor has not paid their own package yet, so you cannot be placed. Please ask them, or contact support.']);
        $this->assertSame(0, OnlinePayment::count());
        Http::assertNothingSent();

        $banned = $this->join('banned_one');
        $banned->forceFill(['status' => User::STATUS_BANNED])->save();
        $this->expectExceptionMessage('A banned account cannot make payments.');
        app(OnlinePayments::class)->start($banned, OnlinePayment::DEPOSIT, null, 500_000, 'https://example.test');
    }

    public function test_waiting_payments_are_checked_with_paystack_and_old_ones_given_up()
    {
        $this->paystack();
        $member = $this->join('waiter');
        $this->actingAs($member)->post('/deposit', ['amount' => '5000']);
        $this->actingAs($member)->post('/deposit', ['amount' => '6000']);
        [$recent, $old] = OnlinePayment::orderBy('id')->get()->all();
        $old->forceFill(['created_at' => now()->subHours(49)])->save();
        $this->answers[$old->reference] = 'ongoing';   // still not paid after 49 hours

        $this->travel(3)->minutes();
        $this->artisan('wiifarm:check-payments')->assertSuccessful();

        $this->assertSame(OnlinePayment::PAID, $recent->fresh()->status);
        $this->assertSame(OnlinePayment::FAILED, $old->fresh()->status);
        $this->assertSame(500_000, $this->main($member));
    }

    public function test_paystack_refusing_shows_its_message_and_leaves_nothing_waiting()
    {
        Http::fake(['paystack.test/*' => Http::response(['status' => false, 'message' => 'Invalid key'], 401)]);
        $person = $this->pending();

        $this->actingAs($person)->post('/package', ['package_id' => $this->package('Starter')->id])
            ->assertSessionHasErrors(['package_id' => 'Paystack said: Invalid key.']);
        $this->assertSame(OnlinePayment::FAILED, OnlinePayment::sole()->status);
    }

    public function test_without_a_paystack_key_online_payment_is_off()
    {
        config(['services.paystack.secret_key' => null]);
        $person = $this->pending();

        $this->actingAs($person)->get('/package')->assertInertia(fn ($page) => $page->where('online', false)->where('current', null)->has('packages', 7));
        $this->post('/package', ['package_id' => $this->package('Starter')->id])->assertSessionHasErrors(['package_id' => 'Online payment is not set up yet. Please try again later.']);
        $this->call('POST', '/webhooks/paystack', [], [], [], ['CONTENT_TYPE' => 'application/json'], '{}')->assertStatus(401);
    }

    public function test_admins_see_payments_and_can_ask_paystack_but_not_mark_them_paid()
    {
        $this->paystack('ongoing');
        $person = $this->pending();
        $this->actingAs($person)->post('/package', ['package_id' => $this->package('Starter')->id]);
        $payment = OnlinePayment::sole();

        $this->actingAs(Admin::factory()->withPermissions(['withdrawals.view'])->create(), 'admin');
        $this->get('/admin/payments')->assertForbidden();

        $this->actingAs(Admin::factory()->withPermissions(['payments.view'])->create(), 'admin');
        $this->get('/admin/payments?status=pending')->assertInertia(fn ($page) => $page->where('payments.data.0.reference', $payment->reference));
        $this->post("/admin/payments/{$payment->id}/check")->assertSessionHas('status', fn ($s) => str_contains($s, '"ongoing"'));
        $this->assertSame(OnlinePayment::PENDING, $payment->fresh()->status);

        $this->paystack();
        $this->post("/admin/payments/{$payment->id}/check");
        $this->assertSame(OnlinePayment::PAID, $payment->fresh()->status);
        $this->assertNotNull($person->fresh()->activated_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'payments.check']);
    }
}
