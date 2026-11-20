<?php

namespace App\Services\Pools;

use App\Models\Ledger\Account;
use App\Models\Ledger\Transaction;
use App\Models\Pools\HeldPayout;
use App\Models\Pools\UnclaimedBonus;
use App\Models\User;
use App\Services\Ledger\Ledger;
use App\Services\Ledger\LedgerException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Pays from a pool line (specification section 7):
 *
 *  1. the line pays what it holds;
 *  2. that pool's own contingency fund covers the rest, never another pool's;
 *  3. if both together cannot cover it, the payout is held. It is paid in
 *     full later, when money arrives, oldest first, and never in part.
 *
 * A payout key is used once: paying with the same key again changes nothing.
 */
class PoolPayouts
{
    public function __construct(private readonly Ledger $ledger) {}

    /** @return Transaction|HeldPayout the payment, or the held payout waiting for money */
    public function pay(string $pool, string $line, Account $to, int $amount, string $type, string $key, ?string $memo = null, ?Model $subject = null): Transaction|HeldPayout
    {
        if ($amount <= 0) {
            throw new LedgerException('A payout must be more than zero.');
        }

        return DB::transaction(function () use ($pool, $line, $to, $amount, $type, $key, $memo, $subject) {
            if ($done = Transaction::where('idempotency_key', 'payout:'.$key)->first()) {
                return $done;
            }
            if ($held = HeldPayout::where('idempotency_key', $key)->first()) {
                return $held;
            }

            [$lineAccount, $contingency] = $this->lock($pool, $line);

            // Someone is already waiting on this line: queue behind them.
            $queued = HeldPayout::where(['pool' => $pool, 'line' => $line, 'status' => HeldPayout::HELD])->exists();

            if (! $queued && $this->affordable($lineAccount, $contingency, $amount)) {
                return $this->transfer($lineAccount, $contingency, $to, $amount, $type, $key, $memo, $subject);
            }

            return HeldPayout::create([
                'pool' => $pool, 'line' => $line, 'to_account_id' => $to->id, 'amount' => $amount,
                'type' => $type, 'idempotency_key' => $key, 'memo' => $memo,
                'subject_type' => $subject ? class_basename($subject) : null, 'subject_id' => $subject?->getKey(),
            ]);
        });
    }

    /**
     * A bonus share nobody can receive goes to that pool's Unclaimed Bonus
     * Reserve, with a record of where it came from (the owner, 19 November
     * 2026). Only what the line holds can move. Returns null when nothing
     * moved.
     *
     * @param  array{bonus: string, generation?: ?int, reason: string, beneficiary?: ?User, source?: ?User}  $origin
     */
    public function toUnclaimed(string $pool, string $line, int $amount, string $key, array $origin, ?string $memo = null, ?Model $subject = null): ?Transaction
    {
        return DB::transaction(function () use ($pool, $line, $amount, $key, $origin, $memo, $subject) {
            if ($done = Transaction::where('idempotency_key', 'unclaimed:'.$key)->first()) {
                return $done;
            }
            $lineAccount = $this->ledger->poolAccount($pool, $line);
            Account::whereKey($lineAccount->id)->lockForUpdate()->first();
            $move = min($amount, $this->ledger->balance($lineAccount));
            if ($move <= 0) {
                return null;
            }

            $tx = $this->ledger->transfer($lineAccount, $this->ledger->poolAccount($pool, Ledger::UNCLAIMED), $move, 'unclaimed_share', 'unclaimed:'.$key, $memo, $subject);
            UnclaimedBonus::create([
                'pool' => $pool, 'line' => $line, 'bonus' => $origin['bonus'], 'generation' => $origin['generation'] ?? null,
                'reason' => $origin['reason'], 'beneficiary_user_id' => ($origin['beneficiary'] ?? null)?->id,
                'source_user_id' => ($origin['source'] ?? null)?->id,
                'subject_type' => $subject ? class_basename($subject) : null, 'subject_id' => $subject?->getKey(),
                'amount' => $move, 'transaction_id' => $tx->id, 'memo' => $memo === null ? null : mb_substr($memo, 0, 250),
            ]);

            return $tx;
        });
    }

    /**
     * A designed leftover (for example a Sub store's unused rebate) stays with
     * the cooperative, in that pool's contingency fund. Only what the line holds can move: a line can
     * hold slightly less than its shares (Pool A's indirect line is 17.99% of
     * the net, ₦116.98 a piece, against shares of up to ₦117.00), and what it
     * lacks was never there. Returns null when the line is empty.
     */
    public function toContingency(string $pool, string $line, int $amount, string $key, ?string $memo = null, ?Model $subject = null): ?Transaction
    {
        return DB::transaction(function () use ($pool, $line, $amount, $key, $memo, $subject) {
            if ($done = Transaction::where('idempotency_key', 'unpaid:'.$key)->first()) {
                return $done;
            }
            [$lineAccount, $contingency] = $this->lock($pool, $line);
            $move = min($amount, $this->ledger->balance($lineAccount));

            return $move > 0 ? $this->ledger->transfer($lineAccount, $contingency, $move, 'unpaid_share', 'unpaid:'.$key, $memo, $subject) : null;
        });
    }

    /** Pays held payouts on one line, oldest first, until one cannot be covered. Returns how many were paid. */
    public function release(string $pool, string $line): int
    {
        $paid = 0;

        while (true) {
            $done = DB::transaction(function () use ($pool, $line) {
                [$lineAccount, $contingency] = $this->lock($pool, $line);
                $next = HeldPayout::where(['pool' => $pool, 'line' => $line, 'status' => HeldPayout::HELD])->orderBy('id')->lockForUpdate()->first();

                if ($next === null || ! $this->affordable($lineAccount, $contingency, $next->amount)) {
                    return false;
                }

                $tx = $this->transfer($lineAccount, $contingency, $next->toAccount, $next->amount, $next->type, $next->idempotency_key, $next->memo, null);
                $next->forceFill(['status' => HeldPayout::PAID, 'transaction_id' => $tx->id, 'paid_at' => now()])->save();

                return true;
            });

            if (! $done) {
                return $paid;
            }
            $paid++;
        }
    }

    /**
     * Tries every line that has held payouts, in the given pools (or all).
     *
     * @param  list<string>|null  $pools
     */
    public function releaseAll(?array $pools = null): int
    {
        $lines = HeldPayout::where('status', HeldPayout::HELD)
            ->when($pools !== null, fn ($q) => $q->whereIn('pool', $pools))
            ->distinct()->get(['pool', 'line']);

        return $lines->sum(fn (HeldPayout $h) => $this->release($h->pool, $h->line));
    }

    /** @return array{0: Account, 1: Account} */
    private function lock(string $pool, string $line): array
    {
        $lineAccount = $this->ledger->poolAccount($pool, $line);
        $contingency = $this->ledger->poolAccount($pool, Ledger::CONTINGENCY);
        Account::whereIn('id', [$lineAccount->id, $contingency->id])->orderBy('id')->lockForUpdate()->get(['id']);

        return [$lineAccount, $contingency];
    }

    private function affordable(Account $line, Account $contingency, int $amount): bool
    {
        return $this->ledger->balance($line) + $this->ledger->balance($contingency) >= $amount;
    }

    private function transfer(Account $line, Account $contingency, Account $to, int $amount, string $type, string $key, ?string $memo, ?Model $subject): Transaction
    {
        $fromLine = min($amount, max(0, $this->ledger->balance($line)));
        $fromContingency = $amount - $fromLine;

        $lines = [];
        if ($fromLine > 0) {
            $lines[] = [$line, -$fromLine];
        }
        if ($fromContingency > 0) {
            $lines[] = [$contingency, -$fromContingency]; // the contingency fund covering this line's shortfall
        }
        $lines[] = [$to, $amount];

        return $this->ledger->post('payout.'.$type, 'payout:'.$key, $lines, $memo, $subject);
    }
}
