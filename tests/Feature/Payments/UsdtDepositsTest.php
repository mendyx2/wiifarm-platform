<?php

namespace Tests\Feature\Payments;

use App\Models\Admin;
use App\Models\Payments\UsdtDeposit;
use App\Models\User;
use App\Services\Ledger\Ledger;
use App\Services\Usdt\TronAddress;
use App\Services\Usdt\TronGrid;
use App\Support\Settings;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Network\NetworkTestCase;

/**
 * USDT (TRC20) deposits (specification section 6): an exact amount per
 * deposit, matched on the chain, never credited twice.
 */
class UsdtDepositsTest extends NetworkTestCase
{
    private string $wiifarm;

    private string $sender;

    /** txid => what TronGrid says about it */
    private array $chain = [];

    private bool $down = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->wiifarm = TronAddress::fromHex('41'.str_repeat('ab', 20));
        $this->sender = TronAddress::fromHex('41'.str_repeat('cd', 20));
        Settings::set('usdt.enabled', true);
        Settings::set('usdt.address', $this->wiifarm);
        Settings::set('usdt.naira_per_usdt', 1600);

        Http::fake(function (Request $request) {
            if ($this->down) {
                return Http::response('oops', 502);
            }
            if (str_contains($request->url(), '/transactions/trc20')) {
                $data = array_map(fn ($txid) => [
                    'transaction_id' => $txid, 'to' => $this->chain[$txid]['to'], 'value' => (string) $this->chain[$txid]['units'],
                    'block_timestamp' => $this->chain[$txid]['time'], 'token_info' => ['address' => $this->chain[$txid]['contract'], 'decimals' => 6, 'symbol' => 'USDT'],
                ], array_keys(array_filter($this->chain, fn ($t) => $t['final'])));

                return Http::response(['success' => true, 'data' => $data, 'meta' => []]);
            }
            $txid = $request['value'];
            $tx = $this->chain[$txid] ?? null;
            $solidity = str_contains($request->url(), '/walletsolidity/');
            if ($tx === null || ($solidity && ! $tx['final'])) {
                return Http::response([]);   // not there, or not final yet
            }
            $pad = fn (string $hex) => str_pad($hex, 64, '0', STR_PAD_LEFT);

            return Http::response([
                'id' => $txid, 'blockNumber' => 1, 'blockTimeStamp' => $tx['time'], 'receipt' => ['result' => 'SUCCESS'],
                'log' => [[
                    'address' => substr(TronAddress::toHex($tx['contract']), 2),
                    'topics' => [TronGrid::TRANSFER_TOPIC, $pad(substr(TronAddress::toHex($this->sender), 2)), $pad(substr(TronAddress::toHex($tx['to']), 2))],
                    'data' => $pad(dechex($tx['units'])),
                ]],
            ]);
        });
    }

    /** A payment on the chain; returns its transaction ID. */
    private function pay(int $units, ?string $to = null, bool $final = true, ?string $contract = null, ?int $timeMs = null): string
    {
        $txid = bin2hex(random_bytes(32));
        $this->chain[$txid] = [
            'units' => $units, 'to' => $to ?? $this->wiifarm, 'final' => $final,
            'contract' => $contract ?? TronGrid::network()['contract'], 'time' => $timeMs ?? now()->getTimestampMs(),
        ];

        return $txid;
    }

    private function start(User $member, string $naira = '16,000'): UsdtDeposit
    {
        $this->actingAs($member)->post('/deposit/usdt', ['usdt_amount' => $naira])->assertSessionHasNoErrors();

        return UsdtDeposit::where('user_id', $member->id)->latest('id')->firstOrFail();
    }

    private function main(User $member): int
    {
        return app(Ledger::class)->balances($member)['main'];
    }

    public function test_each_deposit_asks_for_its_own_exact_amount()
    {
        $one = $this->start($this->join('usdt_one'));
        $two = $this->start($this->join('usdt_two'));

        // ₦16,000 at ₦1,600 = 10 USDT, plus 0.01 to 0.99 so no two open deposits ask the same.
        foreach ([$one, $two] as $d) {
            $this->assertGreaterThanOrEqual(10_010_000, $d->units);
            $this->assertLessThanOrEqual(10_990_000, $d->units);
            $this->assertSame(0, $d->units % 10_000);
            $this->assertSame(1_600_000, $d->amount);
        }
        $this->assertNotSame($one->units, $two->units);
        $this->actingAs($one->user)->get('/deposit')->assertInertia(fn ($page) => $page
            ->where('usdt.open.address', $this->wiifarm)->where('usdt.open.usdt', UsdtDeposit::usdt($one->units)));
    }

    public function test_the_exact_payment_is_credited_once_and_its_id_never_again()
    {
        $member = $this->join('usdt_one');
        $deposit = $this->start($member);
        $txid = $this->pay($deposit->units);

        $this->post('/deposit/usdt/claim', ['txid' => strtoupper($txid)])->assertSessionHasNoErrors()->assertSessionHas('status', fn ($s) => str_contains($s, '₦16,000.00 has been added'));
        $this->assertSame(1_600_000, $this->main($member));
        $this->assertSame(UsdtDeposit::PAID, $deposit->fresh()->status);

        $this->post('/deposit/usdt/claim', ['txid' => $txid])->assertSessionHasErrors('txid');
        $this->artisan('wiifarm:usdt-scan')->assertSuccessful();
        $this->assertSame(1_600_000, $this->main($member));
        $this->assertDatabaseHas('message_log', ['event' => 'deposit.received', 'user_id' => $member->id]);
        $this->artisan('wiifarm:ledger-check')->assertSuccessful();
    }

    public function test_a_payment_pasted_by_someone_else_goes_to_the_deposit_it_fits()
    {
        $owner = $this->join('usdt_owner');
        $deposit = $this->start($owner);
        $txid = $this->pay($deposit->units);
        $thief = $this->join('usdt_thief');
        $this->start($thief);

        $this->actingAs($thief)->post('/deposit/usdt/claim', ['txid' => $txid])->assertSessionHas('status', fn ($s) => str_contains($s, 'belongs to another member'));
        $this->assertSame(1_600_000, $this->main($owner));
        $this->assertSame(0, $this->main($thief));
    }

    public function test_the_scan_finds_unreported_payments_and_records_strangers()
    {
        $member = $this->join('usdt_one');
        $deposit = $this->start($member);
        $this->pay($deposit->units);
        $stranger = $this->pay(5_000_000);   // fits no deposit

        $this->artisan('wiifarm:usdt-scan')->expectsOutputToContain('2 USDT payment(s) recorded')->assertSuccessful();
        $this->assertSame(1_600_000, $this->main($member));
        $this->assertDatabaseHas('usdt_transfers', ['txid' => $stranger, 'status' => 'unmatched', 'units' => 5_000_000]);

        $this->actingAs(Admin::factory()->withPermissions(['deposits.view'])->create(), 'admin')->get('/admin/usdt')
            ->assertInertia(fn ($page) => $page->where('unmatched.0.txid', $stranger)->where('unmatched.0.usdt', '5'));
    }

    public function test_a_wrong_amount_goes_to_an_admin_who_approves_or_refuses()
    {
        $member = $this->join('usdt_one');
        $deposit = $this->start($member);
        $txid = $this->pay($deposit->units - 1_000_000);   // 1 USDT short

        $this->post('/deposit/usdt/claim', ['txid' => $txid])->assertSessionHas('status', fn ($s) => str_contains($s, 'an admin will check it'));
        $this->assertSame([UsdtDeposit::REVIEW, 0], [$deposit->fresh()->status, $this->main($member)]);

        $admin = Admin::factory()->withPermissions(['deposits.view', 'deposits.decide'])->create();
        $this->actingAs($admin, 'admin')->get('/admin/usdt')->assertInertia(fn ($page) => $page->where('deposits.data.0.id', $deposit->id));
        $this->post("/admin/usdt/{$deposit->id}/approve", ['amount' => '14,400'])->assertSessionHasNoErrors();
        $this->assertSame(1_440_000, $this->main($member));
        $this->assertSame(1_440_000, $deposit->fresh()->credited);
        $this->assertDatabaseHas('audit_logs', ['action' => 'deposits.usdt_approve']);

        // A second one, refused: nothing added, and the payment is free for its real owner to claim.
        $other = $this->start($this->join('usdt_two'));
        $late = $this->pay($other->units + 2_000_000);
        $this->actingAs($other->user)->post('/deposit/usdt/claim', ['txid' => $late]);
        $this->actingAs($admin, 'admin')->post("/admin/usdt/{$other->id}/reject", ['reason' => 'Not your payment.'])->assertSessionHasNoErrors();
        $this->assertSame(UsdtDeposit::REJECTED, $other->fresh()->status);
        $this->assertDatabaseHas('usdt_transfers', ['txid' => $late, 'status' => 'unmatched', 'usdt_deposit_id' => null]);
        $this->artisan('wiifarm:ledger-check')->assertSuccessful();
    }

    public function test_fake_tokens_unfinished_payments_and_an_unreachable_network_credit_nothing()
    {
        $member = $this->join('usdt_one');
        $deposit = $this->start($member);
        $fakeToken = TronAddress::fromHex('41'.str_repeat('ef', 20));

        $this->post('/deposit/usdt/claim', ['txid' => $this->pay($deposit->units, contract: $fakeToken)])->assertSessionHasErrors('txid');
        $this->post('/deposit/usdt/claim', ['txid' => $this->pay($deposit->units, to: $this->sender)])->assertSessionHasErrors('txid');
        $this->post('/deposit/usdt/claim', ['txid' => $this->pay($deposit->units, final: false)])->assertSessionHasErrors(['txid' => 'Your payment is on its way but not final yet. Try again in a minute.']);
        $this->post('/deposit/usdt/claim', ['txid' => str_repeat('0', 64)])->assertSessionHasErrors(['txid' => 'This transaction is not on the Tron network. Check the ID.']);
        $this->post('/deposit/usdt/claim', ['txid' => 'not-an-id'])->assertSessionHasErrors('txid');

        $this->down = true;
        $this->post('/deposit/usdt/claim', ['txid' => $this->pay($deposit->units)])->assertSessionHasErrors('txid');
        $this->artisan('wiifarm:usdt-scan')->assertSuccessful();

        $this->assertSame(0, $this->main($member));
        $this->assertSame(0, DB::table('usdt_transfers')->count());
    }

    public function test_settings_need_a_valid_address_a_rate_and_permission()
    {
        $this->actingAs(Admin::factory()->withPermissions(['deposits.view'])->create(), 'admin')
            ->put('/admin/usdt/settings', ['address' => $this->wiifarm, 'naira_per_usdt' => 1500, 'enabled' => true])->assertForbidden();

        $admin = Admin::factory()->withPermissions(['deposits.view', 'deposits.methods'])->create();
        $this->actingAs($admin, 'admin')->put('/admin/usdt/settings', ['address' => 'TNotAnAddress', 'naira_per_usdt' => 1500, 'enabled' => true])->assertSessionHasErrors('address');
        $this->put('/admin/usdt/settings', ['address' => '', 'naira_per_usdt' => 1500, 'enabled' => true])->assertSessionHasErrors('enabled');
        $this->put('/admin/usdt/settings', ['address' => $this->wiifarm, 'naira_per_usdt' => 1550.5, 'enabled' => false])->assertSessionHasNoErrors();

        $this->assertFalse(Settings::get('usdt.enabled'));
        $this->assertSame(1550.5, (float) Settings::get('usdt.naira_per_usdt'));
        $this->actingAs($this->join('usdt_one'))->post('/deposit/usdt', ['usdt_amount' => '16000'])->assertSessionHasErrors('usdt_amount');
    }
}
