<?php

namespace App\Services\Usdt;

use App\Models\Admin;
use App\Models\Payments\UsdtDeposit;
use App\Models\User;
use App\Services\Admin\Audit;
use App\Services\Ledger\Ledger;
use App\Services\Messages\Messages;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * USDT (TRC20) deposits to Wiifarm's one address (specification section 6;
 * the owner's design for the current system, rebuilt here).
 *
 * Anyone can see every payment to a public address, so "paste a transaction
 * ID" alone is not safe. Instead each deposit is asked for an amount no other
 * open deposit is asked for (the naira amount at the admin's rate, rounded up
 * to the cent, plus 0.01 to 0.99 USDT), and a payment is credited only when
 * it is final, in Tether's official token, to the address shown, exactly that
 * amount, and sent inside that deposit's hour. Anything else goes to an
 * admin. A transaction ID is recorded once, so it is never credited twice.
 */
class UsdtDeposits
{
    public function __construct(private readonly TronGrid $tron, private readonly Ledger $ledger, private readonly Audit $audit) {}

    public static function ready(): bool
    {
        return (bool) Settings::get('usdt.enabled') && TronAddress::isValid((string) Settings::get('usdt.address')) && self::rateKobo() > 0;
    }

    public static function rateKobo(): int
    {
        return (int) round(((float) Settings::get('usdt.naira_per_usdt')) * 100);
    }

    /** Starts a deposit of a naira amount: the exact USDT to send, for one hour. */
    public function start(User $member, int $kobo): UsdtDeposit
    {
        if (! self::ready()) {
            throw new InvalidArgumentException('USDT deposits are not available at the moment.');
        }
        [$min, $max] = [config('payments.deposit_min'), config('payments.deposit_max')];
        if ($kobo < $min || $kobo > $max) {
            throw new InvalidArgumentException(sprintf('Deposit between %s and %s.', Messages::naira($min), Messages::naira($max)));
        }
        $rate = self::rateKobo();
        $address = (string) Settings::get('usdt.address');
        // In whole cents of USDT (1 cent = 10,000 units), rounded up so Wiifarm is never short.
        $base = (int) ceil($kobo * 1_000_000 / $rate / 10_000) * 10_000;

        return Cache::lock('usdt:amounts', 10)->block(5, function () use ($member, $kobo, $rate, $address, $base) {
            $now = CarbonImmutable::now();
            $taken = UsdtDeposit::where('address', $address)->where('status', UsdtDeposit::WAITING)
                ->where('expires_at', '>', $now->subSeconds(config('usdt.skew_seconds')))
                ->whereBetween('units', [$base + 10_000, $base + 990_000])->pluck('units')->all();
            $free = array_values(array_diff(array_map(fn ($c) => $base + $c * 10_000, range(1, 99)), $taken));
            if ($free === []) {
                throw new InvalidArgumentException('Many deposits of this amount are open right now. Please wait a few minutes, or change the amount slightly.');
            }

            return UsdtDeposit::create([
                'user_id' => $member->id, 'amount' => $kobo, 'rate_kobo' => $rate, 'units' => $free[random_int(0, count($free) - 1)],
                'address' => $address, 'status' => UsdtDeposit::WAITING, 'expires_at' => $now->addMinutes(config('usdt.window_minutes')),
            ]);
        });
    }

    /**
     * A member gives the transaction ID of their payment. Returns what
     * happened, in words for the member.
     *
     * @throws TronGridUnavailable
     */
    public function claim(User $member, string $txid): string
    {
        $txid = strtolower(trim($txid));
        if (! preg_match('/^[0-9a-f]{64}$/', $txid)) {
            throw new InvalidArgumentException('A transaction ID is 64 letters and digits (0-9, a-f). Copy it from your wallet.');
        }
        if ($seen = DB::table('usdt_transfers')->where('txid', $txid)->first()) {
            if ($seen->status !== 'unmatched') {
                throw new InvalidArgumentException('This payment has already been recorded.');
            }
        }
        $tx = $this->tron->transaction($txid);
        match ($tx['state']) {
            'missing' => throw new InvalidArgumentException('This transaction is not on the Tron network. Check the ID.'),
            'pending' => throw new InvalidArgumentException('Your payment is on its way but not final yet. Try again in a minute.'),
            'failed' => throw new InvalidArgumentException('This transaction failed on the network, so no money moved.'),
            default => null,
        };
        $transfer = $this->toWiifarm($tx['transfers']);
        if ($transfer === null) {
            throw new InvalidArgumentException("This transaction did not send Tether's USDT to Wiifarm's address.");
        }

        return $this->record($txid, $transfer, $tx['time_ms'], $member);
    }

    /** Finds payments nobody reported, and closes deposits whose hour has passed. Returns how many payments were recorded. */
    public function scan(): int
    {
        UsdtDeposit::where('status', UsdtDeposit::WAITING)->where('expires_at', '<', now()->subSeconds(config('usdt.skew_seconds')))
            ->update(['status' => UsdtDeposit::EXPIRED]);
        if (! self::ready()) {
            return 0;
        }
        $recorded = 0;
        $address = (string) Settings::get('usdt.address');
        foreach ($this->tron->incoming($address, now()->subDay()->getTimestampMs()) as $row) {
            if (DB::table('usdt_transfers')->where('txid', $row['txid'])->exists()) {
                continue;
            }
            // The list is never trusted on its own: each payment is read again.
            $tx = $this->tron->transaction($row['txid']);
            if ($tx['state'] === 'confirmed' && ($transfer = $this->toWiifarm($tx['transfers'])) !== null) {
                $this->record($row['txid'], $transfer, $tx['time_ms'], null);
                $recorded++;
            }
        }

        return $recorded;
    }

    /** An admin credits a deposit under review, with the naira amount they decide. */
    public function approve(UsdtDeposit $deposit, Admin $admin, int $kobo): UsdtDeposit
    {
        if ($kobo <= 0) {
            throw new InvalidArgumentException('Enter the naira amount to add.');
        }

        return DB::transaction(function () use ($deposit, $admin, $kobo) {
            $deposit = UsdtDeposit::with('user')->lockForUpdate()->findOrFail($deposit->id);
            if ($deposit->status !== UsdtDeposit::REVIEW) {
                throw new InvalidArgumentException('This deposit is not waiting for a decision.');
            }
            $this->credit($deposit, $kobo, $admin);
            DB::table('usdt_transfers')->where('txid', $deposit->txid)->update(['status' => 'credited', 'updated_at' => now()]);
            $this->audit->record($admin, 'deposits.usdt_approve', $deposit, ['status' => UsdtDeposit::REVIEW], ['status' => UsdtDeposit::PAID, 'credited' => $kobo]);

            return $deposit;
        });
    }

    /** Refused: nothing is added, and the payment is freed so the member it belongs to can still claim it. */
    public function reject(UsdtDeposit $deposit, Admin $admin, string $reason): UsdtDeposit
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException('Give a reason. The member sees it.');
        }

        return DB::transaction(function () use ($deposit, $admin, $reason) {
            $deposit = UsdtDeposit::with('user')->lockForUpdate()->findOrFail($deposit->id);
            if ($deposit->status !== UsdtDeposit::REVIEW) {
                throw new InvalidArgumentException('This deposit is not waiting for a decision.');
            }
            DB::table('usdt_transfers')->where('txid', $deposit->txid)->update(['status' => 'unmatched', 'usdt_deposit_id' => null, 'updated_at' => now()]);
            $deposit->forceFill(['status' => UsdtDeposit::REJECTED, 'note' => mb_substr($reason, 0, 250), 'txid' => null, 'decided_by_admin_id' => $admin->id])->save();
            $this->audit->record($admin, 'deposits.usdt_reject', $deposit, ['status' => UsdtDeposit::REVIEW], ['status' => UsdtDeposit::REJECTED], $reason);
            app(Messages::class)->send($deposit->user, 'deposit.rejected', ['amount' => Messages::naira($deposit->amount), 'reason' => $reason]);

            return $deposit;
        });
    }

    /** The one USDT transfer to Wiifarm's address in a transaction (current or earlier address). */
    private function toWiifarm(array $transfers): ?array
    {
        $ours = UsdtDeposit::query()->distinct()->pluck('address')->push((string) Settings::get('usdt.address'))->filter()->unique()->all();
        foreach ($transfers as $t) {
            if (in_array($t['to'], $ours, true) && $t['units'] > 0) {
                return $t;
            }
        }

        return null;
    }

    /** Matches a final payment to the deposit it fits, or sends it to an admin. */
    private function record(string $txid, array $transfer, ?int $timeMs, ?User $claimant): string
    {
        return DB::transaction(function () use ($txid, $transfer, $timeMs, $claimant) {
            $sent = $timeMs ? CarbonImmutable::createFromTimestampMs($timeMs) : null;
            $fits = $sent === null ? null : UsdtDeposit::with('user')->lockForUpdate()
                ->whereIn('status', [UsdtDeposit::WAITING, UsdtDeposit::EXPIRED])
                ->where('address', $transfer['to'])->where('units', $transfer['units'])
                ->where('created_at', '<=', $sent->addSeconds(config('usdt.skew_seconds')))->where('expires_at', '>=', $sent)
                ->orderBy('id')->first();

            $row = ['address' => $transfer['to'], 'sender' => $transfer['from'], 'units' => $transfer['units'], 'sent_at' => $sent, 'updated_at' => now()];
            $details = ['txid' => $txid, 'received_units' => $transfer['units'], 'sender' => $transfer['from'], 'sent_at' => $sent];

            if ($fits) {
                $this->saveTransfer($txid, $row + ['status' => 'credited', 'usdt_deposit_id' => $fits->id]);
                $fits->forceFill($details)->save();
                $this->credit($fits, $fits->amount, null);
                if ($claimant && $fits->user_id !== $claimant->id) {
                    return 'This payment belongs to another member\'s deposit and was credited to it. If you paid, enter your own transaction ID.';
                }

                return 'Payment found: '.Messages::naira($fits->amount).' has been added to your main balance.';
            }

            // A member's own payment that does not fit exactly (wrong amount, or after the hour): an admin decides.
            $theirs = $claimant ? UsdtDeposit::lockForUpdate()->where('user_id', $claimant->id)
                ->whereIn('status', [UsdtDeposit::WAITING, UsdtDeposit::EXPIRED])
                ->where('created_at', '>=', now()->subDays(config('usdt.claim_days')))->latest('id')->first() : null;
            if ($theirs) {
                $this->saveTransfer($txid, $row + ['status' => 'review', 'usdt_deposit_id' => $theirs->id]);
                $theirs->forceFill($details + ['status' => UsdtDeposit::REVIEW])->save();

                return 'Your payment does not match the amount or time asked, so an admin will check it. You will be told the result.';
            }
            $this->saveTransfer($txid, $row + ['status' => 'unmatched', 'usdt_deposit_id' => null]);
            if ($claimant) {
                throw new InvalidArgumentException('You have no open USDT deposit for this payment. Start a deposit first, or contact support.');
            }

            return 'unmatched';
        });
    }

    private function saveTransfer(string $txid, array $row): void
    {
        $existing = DB::table('usdt_transfers')->where('txid', $txid)->first();
        if ($existing && $existing->status !== 'unmatched') {
            throw new InvalidArgumentException('This payment has already been recorded.');
        }
        try {
            $existing
                ? DB::table('usdt_transfers')->where('id', $existing->id)->update($row)
                : DB::table('usdt_transfers')->insert($row + ['txid' => $txid, 'created_at' => now()]);
        } catch (UniqueConstraintViolationException) {
            throw new InvalidArgumentException('This payment has already been recorded.');
        }
    }

    private function credit(UsdtDeposit $deposit, int $kobo, ?Admin $admin): void
    {
        $tx = $this->ledger->transfer(
            $this->ledger->systemAccount('external'), $this->ledger->memberAccount($deposit->user, 'main'), $kobo,
            'deposit', 'deposit:usdt:'.$deposit->txid, "USDT deposit #{$deposit->id}: ".UsdtDeposit::usdt((int) $deposit->received_units)." USDT, tx {$deposit->txid}", $deposit, $admin?->id,
        );
        $deposit->forceFill(['status' => UsdtDeposit::PAID, 'credited' => $kobo, 'transaction_id' => $tx->id, 'decided_by_admin_id' => $admin?->id])->save();
        app(Messages::class)->send($deposit->user, 'deposit.received', ['amount' => Messages::naira($kobo)]);
    }
}
