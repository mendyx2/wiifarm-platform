<?php

namespace Tests\Feature\Ledger;

use App\Models\Ledger\Entry;
use App\Models\Ledger\Transaction;
use App\Models\User;
use App\Services\Ledger\InsufficientFunds;
use App\Services\Ledger\Ledger;
use App\Services\Ledger\LedgerException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The ledger's rules (specification section 1).
 */
class LedgerTest extends TestCase
{
    use RefreshDatabase;

    private Ledger $ledger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ledger = app(Ledger::class);
    }

    private function fund(User $member, int $kobo, string $key = 'fund'): Transaction
    {
        return $this->ledger->transfer($this->ledger->systemAccount('external'), $this->ledger->memberAccount($member, 'main'), $kobo, 'test.fund', $key);
    }

    public function test_a_balance_is_the_sum_of_its_entries()
    {
        $amaka = User::factory()->create();
        $this->fund($amaka, 500_000, 'fund-1');
        $this->fund($amaka, 250_050, 'fund-2');

        $this->assertSame(750_050, $this->ledger->balance($this->ledger->memberAccount($amaka, 'main')));
        $this->assertSame(750_050, $this->ledger->balances($amaka)['main']);
        $this->assertSame(0, $this->ledger->balances($amaka)['bonus.direct']);
        $this->assertSame(-750_050, $this->ledger->balance($this->ledger->systemAccount('external')));
    }

    public function test_the_same_trigger_never_posts_twice()
    {
        $amaka = User::factory()->create();

        $first = $this->fund($amaka, 500_000, 'registration:7:direct');
        $again = $this->fund($amaka, 500_000, 'registration:7:direct');

        $this->assertFalse($first->wasAlreadyPosted);
        $this->assertTrue($again->wasAlreadyPosted);
        $this->assertSame($first->id, $again->id);
        $this->assertSame(1, Transaction::count());
        $this->assertSame(500_000, $this->ledger->balances($amaka)['main']);
    }

    public function test_the_same_trigger_with_different_amounts_is_refused()
    {
        $amaka = User::factory()->create();
        $this->fund($amaka, 500_000, 'registration:7:direct');

        $this->expectException(LedgerException::class);
        $this->fund($amaka, 600_000, 'registration:7:direct');
    }

    public function test_lines_must_add_up_to_zero()
    {
        $amaka = User::factory()->create();

        $this->expectException(LedgerException::class);
        $this->ledger->post('test', 'k', [
            [$this->ledger->systemAccount('external'), -100],
            [$this->ledger->memberAccount($amaka, 'main'), 101],
        ]);
    }

    public function test_a_member_wallet_cannot_go_below_zero()
    {
        $amaka = User::factory()->create();
        $this->fund($amaka, 1_000);

        try {
            $this->ledger->transfer($this->ledger->memberAccount($amaka, 'main'), $this->ledger->systemAccount('external'), 1_001, 'test.withdraw', 'w-1');
            $this->fail('The wallet went below zero.');
        } catch (InsufficientFunds) {
        }

        $this->assertSame(1_000, $this->ledger->balances($amaka)['main']);
        $this->assertSame(1, Transaction::count());

        // Exactly to zero is fine.
        $this->ledger->transfer($this->ledger->memberAccount($amaka, 'main'), $this->ledger->systemAccount('external'), 1_000, 'test.withdraw', 'w-2');
        $this->assertSame(0, $this->ledger->balances($amaka)['main']);
    }

    public function test_platform_accounts_may_go_below_zero_so_overruns_show()
    {
        $amaka = User::factory()->create();

        $this->ledger->transfer($this->ledger->systemAccount('crp.direct'), $this->ledger->memberAccount($amaka, 'bonus.direct'), 375_000, 'bonus.direct', 'reg:1:direct');

        $this->assertSame(-375_000, $this->ledger->balance($this->ledger->systemAccount('crp.direct')));
    }

    public function test_a_failed_posting_writes_nothing_even_inside_a_bigger_payout()
    {
        $amaka = User::factory()->create();
        $tunde = User::factory()->create();

        try {
            DB::transaction(function () use ($amaka, $tunde) {
                $this->fund($amaka, 1_000, 'pay-amaka');
                // Tunde has nothing, so this fails, and Amaka's credit is undone with it.
                $this->ledger->transfer($this->ledger->memberAccount($tunde, 'main'), $this->ledger->systemAccount('external'), 1, 'test', 'take-tunde');
            });
        } catch (InsufficientFunds) {
        }

        $this->assertSame(0, Transaction::count());
        $this->assertSame(0, Entry::count());
    }

    public function test_unknown_wallets_and_bad_lines_are_refused()
    {
        $amaka = User::factory()->create();

        foreach ([
            fn () => $this->ledger->memberAccount($amaka, 'bonus.made_up'),
            fn () => $this->ledger->systemAccount('pocket'),
            fn () => $this->ledger->post('test', 'k1', [[$this->ledger->systemAccount('external'), 0], [$this->ledger->memberAccount($amaka, 'main'), 0]]),
            fn () => $this->ledger->post('test', 'k2', [[$this->ledger->memberAccount($amaka, 'main'), 5]]),
            fn () => $this->ledger->post('test', '', [[$this->ledger->systemAccount('external'), -5], [$this->ledger->memberAccount($amaka, 'main'), 5]]),
            fn () => $this->ledger->transfer($this->ledger->systemAccount('external'), $this->ledger->memberAccount($amaka, 'main'), -5, 'test', 'k3'),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('A bad posting was accepted.');
            } catch (LedgerException) {
            }
        }

        $this->assertSame(0, Transaction::count());
    }

    public function test_entries_and_transactions_cannot_be_changed_or_deleted()
    {
        $transaction = $this->fund(User::factory()->create(), 1_000);
        $entry = $transaction->entries()->first();
        $amount = $entry->amount;

        foreach ([fn () => $entry->update(['amount' => 5]), fn () => $entry->delete(), fn () => $transaction->update(['memo' => 'x']), fn () => $transaction->delete()] as $attempt) {
            try {
                $attempt();
                $this->fail('The ledger was changed.');
            } catch (\LogicException) {
            }
        }

        $this->assertSame($amount, $entry->fresh()->amount);
    }

    public function test_the_nightly_check_passes_on_healthy_books_and_catches_a_broken_one()
    {
        $amaka = User::factory()->create();
        $this->fund($amaka, 1_000);

        $this->artisan('wiifarm:ledger-check')->assertSuccessful();

        // Someone writes to the database behind the ledger's back.
        DB::table('ledger_entries')->insert(['transaction_id' => Transaction::first()->id, 'account_id' => $this->ledger->memberAccount($amaka, 'main')->id, 'amount' => 99, 'created_at' => now()]);

        $this->artisan('wiifarm:ledger-check')->assertFailed();
    }
}
