<?php

namespace App\Services\Pools;

use App\Models\Ledger\Account;
use App\Models\Ledger\Transaction;
use App\Models\Pools\AllocationSchedule;
use App\Models\Pools\Manufacturer;
use App\Services\Ledger\Ledger;
use App\Services\Ledger\LedgerException;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;

/**
 * Splits a confirmed payment between the manufacturing company and the
 * cooperative's pool ledgers, by the allocation schedule in force
 * (specification section 7, Allocation Blueprint V3).
 *
 * One ledger transaction per payment: the parts add up to the payment
 * exactly, the rounding remainder goes to the receiving pool's contingency
 * fund, and the same payment key never splits twice. Afterwards, payouts that
 * were held for lack of money are paid if the new money covers them.
 *
 *     $split->registration(1_500_000, $manufacturer, 'paystack:ref_123', $packageChange);
 */
class PaymentSplit
{
    public function __construct(private readonly Ledger $ledger, private readonly PoolPayouts $payouts) {}

    /** A new member's first purchase (their package). */
    public function registration(int $amount, Manufacturer $manufacturer, string $key, ?Model $subject = null): Transaction
    {
        return $this->member('registration', $amount, $manufacturer, $key, $subject);
    }

    /** The price difference of a package upgrade. */
    public function upgrade(int $amount, Manufacturer $manufacturer, string $key, ?Model $subject = null): Transaction
    {
        return $this->member('upgrade', $amount, $manufacturer, $key, $subject);
    }

    /** A member's repurchase of products. */
    public function repurchase(int $amount, Manufacturer $manufacturer, string $key, ?Model $subject = null): Transaction
    {
        return $this->member('repurchase', $amount, $manufacturer, $key, $subject);
    }

    /**
     * A sale in the public shop (section 8). It stays outside the cooperative:
     * the affiliate's share (20% of the margin above the member price, only
     * when an affiliate sent the buyer) and the largest stockist rebate are
     * held until pickup; everything else belongs to the manufacturing company.
     */
    public function publicSale(int $units, int $unitPrice, Manufacturer $manufacturer, bool $viaAffiliate, string $key, ?Model $subject = null): Transaction
    {
        $rates = $this->schedule()->rates;
        $amount = $units * $unitPrice;
        $margin = max(0, $units * ($unitPrice - $rates['public']['member_unit_price']));
        $affiliate = $viaAffiliate ? Money::share($margin, $rates['public']['affiliate_share_of_margin']) : 0;
        $stockist = $units * max($rates['stockist_rebate']);

        if ($units <= 0 || $amount - $affiliate - $stockist < 0) {
            throw new LedgerException('A public sale must be at least one unit, priced above what it holds back.');
        }

        return $this->post('split.public', $key, $amount, $manufacturer, $amount - $affiliate - $stockist, [
            'P.affiliate' => $affiliate,
            'P.stockist' => $stockist,
        ], $subject);
    }

    private function member(string $kind, int $amount, Manufacturer $manufacturer, string $key, ?Model $subject): Transaction
    {
        if ($amount <= 0) {
            throw new LedgerException('The payment must be more than zero.');
        }

        $rates = $this->schedule()->rates;
        $halves = Money::allocate($amount, $rates['member_split'], 'cooperative');
        $parts = $this->cooperative($kind, $halves['cooperative'], $rates);

        return $this->post('split.'.$kind, $key, $amount, $manufacturer, $halves['manufacturer'], $parts, $subject);
    }

    /**
     * How the cooperative's share is divided among the pool lines.
     *
     * @param  array<string, mixed>  $rates
     * @return array<string, int> "pool.line" => kobo
     */
    private function cooperative(string $kind, int $amount, array $rates): array
    {
        $parts = [];
        $add = function (array $more) use (&$parts) {
            foreach ($more as $k => $v) {
                $parts[$k] = ($parts[$k] ?? 0) + $v;
            }
        };

        $s = $rates[$kind];
        $home = ['registration' => 'A', 'upgrade' => 'E', 'repurchase' => 'C'][$kind];
        $top = $this->divide($amount, $s['top'], "{$home}.contingency");

        foreach ($top as $target => $value) {
            match ($target) {
                'B' => $add($this->divide($value, $rates['strategic_reserve'], 'B.contingency')),
                'L' => $add($this->divide($value, $rates['executive_leaders'], 'L.contingency')),
                'net' => $add($this->divide($value, $s['net'], "{$home}.contingency")),
                default => $add([$target => $value]),
            };
        }

        return $parts;
    }

    /**
     * Divides an amount by percentages that add up to 100; each part rounds
     * down and the remainder goes to $remainderTo.
     *
     * @param  array<string, string>  $percents
     * @return array<string, int>
     */
    private function divide(int $amount, array $percents, string $remainderTo): array
    {
        if (array_sum(array_map(Money::basisPoints(...), $percents)) !== 10_000) {
            throw new LedgerException('The allocation schedule\'s percentages must add up to 100.');
        }

        $parts = array_map(fn (string $p) => Money::share($amount, $p), $percents);
        $parts[$remainderTo] = ($parts[$remainderTo] ?? 0) + $amount - array_sum($parts);

        return $parts;
    }

    /** @param  array<string, int>  $parts */
    private function post(string $type, string $key, int $amount, Manufacturer $manufacturer, int $manufacturerShare, array $parts, ?Model $subject): Transaction
    {
        $schedule = $this->schedule();
        $credits = [];
        $add = function (Account $account, int $value) use (&$credits) {
            $credits[$account->id] ??= [$account, 0];
            $credits[$account->id][1] += $value;
        };

        $add($this->ledger->manufacturerAccount($manufacturer), $manufacturerShare);
        foreach ($parts as $poolLine => $value) {
            [$pool, $line] = explode('.', $poolLine, 2);
            $add($this->ledger->poolAccount($pool, $line), $value);
        }

        $lines = [[$this->ledger->systemAccount('external'), -$amount]];
        foreach ($credits as [$account, $value]) {
            if ($value !== 0) {
                $lines[] = [$account, $value];
            }
        }

        $transaction = $this->ledger->post($type, 'split:'.$key, $lines, "Allocation schedule v{$schedule->version}", $subject);

        if (! $transaction->wasAlreadyPosted) {
            $pools = array_unique(array_map(fn ($k) => explode('.', $k)[0], array_keys($parts)));
            $this->payouts->releaseAll($pools);
        }

        return $transaction;
    }

    private function schedule(): AllocationSchedule
    {
        return AllocationSchedule::current();
    }
}
