<?php

namespace App\Services\Payments;

use App\Models\Package;
use App\Models\Payments\OnlinePayment;
use App\Models\Shop\Order;
use App\Models\User;
use App\Services\Ledger\Ledger;
use App\Services\Messages\Messages;
use App\Services\Network\PlacementException;
use App\Services\Network\Registration;
use App\Services\Network\Upgrade;
use App\Services\Shop\Orders;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Paying online through Paystack (specification sections 2 and 6): a
 * member's package, an upgrade, or a deposit to the main balance.
 *
 *  1. start() records the payment and returns Paystack's page;
 *  2. the payment is confirmed only by Paystack itself: its signed message
 *     (the webhook), or our own server asking Paystack. A browser coming
 *     back is never trusted on its own;
 *  3. confirm() then does what was paid for, once only, and only if Paystack
 *     received exactly the amount asked for, in naira.
 *
 * If what was paid for can no longer happen (the member became a member some
 * other way, or upgraded already), the money is not lost: it goes to their
 * main balance, and the payment says why.
 */
class OnlinePayments
{
    public function __construct(
        private readonly Paystack $paystack,
        private readonly Ledger $ledger,
        private readonly Registration $registration,
        private readonly Upgrade $upgrade,
        private readonly Orders $orders,
    ) {}

    /** @return array{0: OnlinePayment, 1: string} the payment and the Paystack page to send the member to */
    public function start(User $member, string $purpose, ?Package $package, ?int $amount, string $callbackUrl): array
    {
        if ($member->isBanned()) {
            throw new PaymentException('A banned account cannot make payments.');
        }

        [$amount, $from] = match ($purpose) {
            OnlinePayment::PACKAGE => $this->forPackage($member, $package),
            OnlinePayment::UPGRADE => $this->forUpgrade($member, $package),
            OnlinePayment::DEPOSIT => [$this->forDeposit($amount), null],
            default => throw new PaymentException('Unknown payment.'),
        };

        $payment = OnlinePayment::create([
            'user_id' => $member->id, 'purpose' => $purpose,
            'package_id' => $purpose === OnlinePayment::DEPOSIT ? null : $package?->id, 'from_package_id' => $from?->id,
            'amount' => $amount, 'reference' => 'WF-'.Str::upper(Str::random(16)), 'status' => OnlinePayment::PENDING,
        ]);

        try {
            $url = $this->paystack->initialize($member->email, $amount, $payment->reference, $callbackUrl, [
                'purpose' => $purpose, 'member' => $member->username, 'payment_id' => $payment->id,
            ]);
        } catch (PaymentException $e) {
            $payment->forceFill(['status' => OnlinePayment::FAILED, 'note' => 'Not started: '.$e->getMessage()])->save();
            throw $e;
        }

        return [$payment, $url];
    }

    /**
     * A public-shop order (section 8), paid by someone without an account.
     *
     * @return array{0: OnlinePayment, 1: string}
     */
    public function startPublic(Order $order, string $email, string $callbackUrl): array
    {
        if ($order->kind !== Order::PUBLIC || $order->status !== Order::PENDING) {
            throw new PaymentException('This order cannot be paid now.');
        }

        $payment = OnlinePayment::create([
            'user_id' => null, 'order_id' => $order->id, 'purpose' => OnlinePayment::PUBLIC_ORDER,
            'amount' => $order->amount, 'reference' => 'WF-'.Str::upper(Str::random(16)), 'status' => OnlinePayment::PENDING,
        ]);

        try {
            $url = $this->paystack->initialize($email, $order->amount, $payment->reference, $callbackUrl, ['purpose' => 'public', 'order' => $order->number]);
        } catch (PaymentException $e) {
            $payment->forceFill(['status' => OnlinePayment::FAILED, 'note' => 'Not started: '.$e->getMessage()])->save();
            throw $e;
        }

        return [$payment, $url];
    }

    /** Asks Paystack about a payment and acts on the answer. */
    public function check(OnlinePayment $payment): OnlinePayment
    {
        return $this->confirm($payment, $this->paystack->verify($payment->reference));
    }

    /**
     * Acts on what Paystack says about a payment. Safe to call any number of
     * times: what was paid for happens once.
     *
     * @param  array{status: string, amount: int, currency: string, channel: ?string, reference: string}  $facts
     */
    public function confirm(OnlinePayment $payment, array $facts): OnlinePayment
    {
        return DB::transaction(function () use ($payment, $facts) {
            $payment = OnlinePayment::with(['user', 'package', 'order'])->lockForUpdate()->findOrFail($payment->id);
            if (in_array($payment->status, [OnlinePayment::PAID, OnlinePayment::MISMATCH], true)) {
                return $payment; // already settled
            }

            $payment->forceFill(['gateway_status' => $facts['status'], 'channel' => $facts['channel'], 'checked_at' => now()]);

            if ($facts['status'] !== 'success') {
                // Still going on at Paystack, or not paid. A failed payment can still turn out paid later.
                if (in_array($facts['status'], ['failed', 'abandoned', 'reversed'], true)) {
                    $payment->status = OnlinePayment::FAILED;
                }
                $payment->save();

                return $payment;
            }

            if ($facts['reference'] !== $payment->reference || $facts['amount'] !== $payment->amount || $facts['currency'] !== 'NGN') {
                $payment->forceFill([
                    'status' => OnlinePayment::MISMATCH,
                    'note' => sprintf('Paystack reports %s %s, but %s was asked for. Nothing was credited; an admin must look into it.', $facts['currency'], number_format($facts['amount'] / 100, 2), '₦'.number_format($payment->amount / 100, 2)),
                ])->save();
                Log::error('Paystack payment did not match', ['reference' => $payment->reference, 'facts' => $facts]);

                return $payment;
            }

            $payment->forceFill(['status' => OnlinePayment::PAID, 'paid_at' => now(), 'note' => $this->deliver($payment)])->save();

            return $payment;
        });
    }

    /** Payments still waiting: ask Paystack, in case its message never came. Returns how many were checked. */
    public function checkWaiting(): int
    {
        $checked = 0;
        $waiting = OnlinePayment::where('status', OnlinePayment::PENDING)
            ->where('created_at', '<', now()->subMinutes(2))
            ->where(fn ($q) => $q->whereNull('checked_at')->orWhere('checked_at', '<', now()->subMinutes(10)))
            ->orderBy('id')->limit(200)->get();

        foreach ($waiting as $payment) {
            try {
                $this->check($payment);
                $checked++;
            } catch (PaymentException) {
                $payment->forceFill(['checked_at' => now()])->save(); // try again later
            }
        }

        // Given up on after the waiting time: Paystack never saw them paid.
        OnlinePayment::where('status', OnlinePayment::PENDING)->where('created_at', '<', now()->subHours(config('payments.check_for_hours')))
            ->update(['status' => OnlinePayment::FAILED, 'note' => 'Not paid within '.config('payments.check_for_hours').' hours.']);

        return $checked;
    }

    /** Does what was paid for. Returns a note when the money went to the main balance instead. */
    private function deliver(OnlinePayment $payment): ?string
    {
        $key = 'paystack:'.$payment->reference;
        $member = $payment->user;

        if ($payment->purpose === OnlinePayment::PUBLIC_ORDER) {
            // Paid is paid: even an order cancelled for waiting too long is honoured, with its pickup code.
            $this->orders->confirmPublicPayment($payment->order, $key);

            return null;
        }

        if ($payment->purpose === OnlinePayment::DEPOSIT) {
            $this->toMainBalance($payment, $key);
            app(Messages::class)->send($member, 'deposit.received', ['amount' => Messages::naira($payment->amount)]);

            return null;
        }

        try {
            if ($payment->purpose === OnlinePayment::PACKAGE) {
                $this->registration->activate($member, $payment->package, $key);
            } else {
                if ($member->package_id !== $payment->from_package_id) {
                    throw new PlacementException("The package changed after this payment was started, so the upgrade to {$payment->package->name} was not made.");
                }
                $this->upgrade->apply($member, $payment->package, $key);
            }

            return null;
        } catch (PlacementException $e) {
            $this->toMainBalance($payment, $key);

            return $e->getMessage().' The money is in the main balance instead.';
        }
    }

    private function toMainBalance(OnlinePayment $payment, string $key): void
    {
        $this->ledger->transfer(
            $this->ledger->systemAccount('external'), $this->ledger->memberAccount($payment->user, 'main'), $payment->amount,
            'deposit', 'deposit:'.$key, $payment->describe().' (Paystack '.$payment->reference.')', $payment,
        );
    }

    /** @return array{0: int, 1: null} */
    private function forPackage(User $member, ?Package $package): array
    {
        if ($member->isActivated()) {
            throw new PaymentException('Your package is already paid.');
        }
        if ($package === null || ! $package->is_active) {
            throw new PaymentException('Choose a package that is on offer.');
        }
        // Checked before paying, so nobody pays and then cannot be placed (open decision 16).
        if ($member->sponsor !== null && ! $member->sponsor->position()->exists()) {
            throw new PaymentException("Your sponsor @{$member->sponsor->username} has not paid their own package yet, so you cannot be placed. Please ask them, or contact support.");
        }

        return [$package->price, null];
    }

    /** @return array{0: int, 1: Package} */
    private function forUpgrade(User $member, ?Package $to): array
    {
        $from = $member->package;
        if (! $member->isActivated() || $from === null) {
            throw new PaymentException('Pay your package first.');
        }
        if (! $from->upgradable) {
            throw new PaymentException("The {$from->name} package cannot be upgraded.");
        }
        if ($to === null || ! $to->is_active || $to->pv <= $from->pv) {
            throw new PaymentException('Choose a higher package that is on offer.');
        }

        return [$to->price - $from->price, $from];
    }

    private function forDeposit(?int $amount): int
    {
        $min = config('payments.deposit_min');
        $max = config('payments.deposit_max');
        if ($amount === null || $amount < $min || $amount > $max) {
            throw new PaymentException(sprintf('Deposit between ₦%s and ₦%s.', number_format($min / 100), number_format($max / 100)));
        }

        return $amount;
    }
}
