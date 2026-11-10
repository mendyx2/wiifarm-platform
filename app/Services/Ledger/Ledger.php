<?php

namespace App\Services\Ledger;

use App\Models\Ledger\Account;
use App\Models\Ledger\Entry;
use App\Models\Ledger\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * The only way money or PV moves (specification section 1).
 *
 *  - Double entry: a transaction's entries add up to zero, per currency, so
 *    money is moved, never created or lost.
 *  - Once only: each transaction carries the key of what triggered it
 *    ("registration:123:direct", "adjust:<form id>"). Posting the same key
 *    again writes nothing and returns the first transaction. The same key with
 *    different amounts is refused as a fault.
 *  - All or nothing: a transaction is written whole, or not at all.
 *  - A member's wallet never goes below zero. Platform accounts may: a pool
 *    line below zero is an overrun, shown in reports (section 7).
 *
 *     $ledger->post('adjustment', 'adjust:'.$formId, [
 *         [$ledger->systemAccount('adjustments'), -50_000],
 *         [$ledger->memberAccount($member, 'main'), 50_000],
 *     ], memo: 'Refund of a double charge', adminId: $admin->id);
 */
class Ledger
{
    public const NGN = 'NGN';

    public const PV = 'PV';

    /**
     * @param  list<array{0: Account, 1: int}>  $lines  each [account, amount]: positive into the account, negative out
     *
     * @throws LedgerException|InsufficientFunds
     */
    public function post(string $type, string $key, array $lines, ?string $memo = null, ?Model $subject = null, ?int $adminId = null): Transaction
    {
        $this->check($key, $lines);
        $fingerprint = $this->fingerprint($type, $lines);

        try {
            return DB::transaction(function () use ($type, $key, $lines, $memo, $subject, $adminId, $fingerprint) {
                if ($existing = Transaction::where('idempotency_key', $key)->first()) {
                    return $this->repeat($existing, $fingerprint);
                }

                // Lock the accounts in a fixed order (by id) so two postings
                // cannot deadlock, then make sure no member wallet goes below zero.
                $ids = collect($lines)->map(fn ($l) => $l[0]->id)->sort()->values();
                Account::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get(['id']);

                foreach ($lines as [$account, $amount]) {
                    if ($amount < 0 && ! $account->isSystem() && $this->balance($account) + $amount < 0) {
                        throw new InsufficientFunds('Not enough in the '.$this->label($account).' for this.');
                    }
                }

                $transaction = Transaction::create([
                    'type' => $type,
                    'idempotency_key' => $key,
                    'fingerprint' => $fingerprint,
                    'memo' => $memo === null ? null : mb_substr($memo, 0, 250),
                    'subject_type' => $subject ? class_basename($subject) : null,
                    'subject_id' => $subject?->getKey(),
                    'admin_id' => $adminId,
                ]);

                foreach ($lines as [$account, $amount]) {
                    Entry::create(['transaction_id' => $transaction->id, 'account_id' => $account->id, 'amount' => $amount]);
                }

                return $transaction;
            });
        } catch (UniqueConstraintViolationException $e) {
            // Two identical postings at the same moment: the other one won.
            $existing = Transaction::where('idempotency_key', $key)->first();
            if ($existing === null) {
                throw $e;
            }

            return $this->repeat($existing, $fingerprint);
        }
    }

    /** Move an amount from one account to another: the common two-line posting. */
    public function transfer(Account $from, Account $to, int $amount, string $type, string $key, ?string $memo = null, ?Model $subject = null, ?int $adminId = null): Transaction
    {
        if ($amount <= 0) {
            throw new LedgerException('The amount must be more than zero.');
        }

        return $this->post($type, $key, [[$from, -$amount], [$to, $amount]], $memo, $subject, $adminId);
    }

    /** A member's wallet, opened the first time it is needed. */
    public function memberAccount(User $member, string $kind): Account
    {
        if (! array_key_exists($kind, config('ledger.member_wallets'))) {
            throw new LedgerException("Unknown wallet [{$kind}].");
        }

        return $this->open('member:'.$member->id, $member->id, $kind, self::NGN);
    }

    /** One of the platform's own accounts. */
    public function systemAccount(string $kind): Account
    {
        if (! array_key_exists($kind, config('ledger.system_accounts'))) {
            throw new LedgerException("Unknown platform account [{$kind}].");
        }

        return $this->open(Account::SYSTEM, null, $kind, self::NGN);
    }

    /** The sum of an account's entries. */
    public function balance(Account $account): int
    {
        return (int) Entry::where('account_id', $account->id)->sum('amount');
    }

    /**
     * Every wallet of a member and its balance, in the order of config/ledger.php.
     * Wallets never used show zero.
     *
     * @return array<string, int>
     */
    public function balances(User $member): array
    {
        $sums = Entry::query()
            ->join('ledger_accounts', 'ledger_accounts.id', '=', 'ledger_entries.account_id')
            ->where('ledger_accounts.holder', 'member:'.$member->id)
            ->groupBy('ledger_accounts.kind')
            ->selectRaw('ledger_accounts.kind as kind, SUM(ledger_entries.amount) as total')
            ->pluck('total', 'kind');

        return collect(config('ledger.member_wallets'))->map(fn ($label, $kind) => (int) ($sums[$kind] ?? 0))->all();
    }

    public function label(Account $account): string
    {
        return $account->isSystem()
            ? config('ledger.system_accounts')[$account->kind] ?? $account->kind
            : config('ledger.member_wallets')[$account->kind] ?? $account->kind;
    }

    /** @param  list<array{0: Account, 1: int}>  $lines */
    private function check(string $key, array $lines): void
    {
        if ($key === '' || strlen($key) > 191) {
            throw new LedgerException('Every posting needs a key of up to 191 characters naming what triggered it.');
        }
        if (count($lines) < 2) {
            throw new LedgerException('A posting needs at least two lines.');
        }

        $seen = [];
        $sums = [];
        foreach ($lines as $line) {
            [$account, $amount] = $line;
            if (! $account instanceof Account || ! $account->exists || ! is_int($amount)) {
                throw new LedgerException('Each line is a saved account and a whole-number amount.');
            }
            if ($amount === 0) {
                throw new LedgerException('A line cannot be zero.');
            }
            if (isset($seen[$account->id])) {
                throw new LedgerException('An account can appear only once in a posting.');
            }
            $seen[$account->id] = true;
            $sums[$account->currency] = ($sums[$account->currency] ?? 0) + $amount;
        }

        foreach ($sums as $currency => $sum) {
            if ($sum !== 0) {
                throw new LedgerException("The {$currency} lines add up to {$sum}, not zero.");
            }
        }
    }

    /** @param  list<array{0: Account, 1: int}>  $lines */
    private function fingerprint(string $type, array $lines): string
    {
        $parts = collect($lines)->map(fn ($l) => $l[0]->id.':'.$l[1])->sort()->implode(',');

        return hash('sha256', $type.'|'.$parts);
    }

    private function repeat(Transaction $existing, string $fingerprint): Transaction
    {
        if (! hash_equals($existing->fingerprint, $fingerprint)) {
            throw new LedgerException("The trigger [{$existing->idempotency_key}] was already posted with different amounts.");
        }

        $existing->wasAlreadyPosted = true;

        return $existing;
    }

    private function open(string $holder, ?int $userId, string $kind, string $currency): Account
    {
        try {
            return Account::firstOrCreate(['holder' => $holder, 'kind' => $kind], ['user_id' => $userId, 'currency' => $currency]);
        } catch (UniqueConstraintViolationException) {
            return Account::where(['holder' => $holder, 'kind' => $kind])->firstOrFail();
        }
    }
}
