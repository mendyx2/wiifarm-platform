<?php

namespace App\Services\Payments;

use App\Models\Admin;
use App\Models\Affiliate;
use App\Models\Ledger\Account;
use App\Models\Payments\Withdrawal;
use App\Models\Payments\WithdrawalMethod;
use App\Models\Stockists\Store;
use App\Models\User;
use App\Services\Admin\Audit;
use App\Services\Ledger\Ledger;
use App\Services\Ledger\LedgerException;
use App\Services\Messages\Messages;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Withdrawals (specification section 6), from a member's main balance, a
 * stockist wallet or an affiliate wallet:
 *
 *  - on request, the amount leaves the wallet at once and waits in a holding
 *    account for an admin;
 *  - the admin approves it once the money is sent (the person receives the
 *    amount less the method's charge; the charge goes to the cooperative), or
 *    rejects it with a reason, and the full amount goes back to the wallet.
 *
 * Who may withdraw (blocked members, KYC) is checked by the caller, which
 * knows whose wallet it is.
 */
class Withdrawals
{
    public function __construct(private readonly Ledger $ledger, private readonly Audit $audit) {}

    /**
     * @param  array<string, string>  $details  the method's form
     */
    public function request(string $holderType, int $holderId, Account $wallet, WithdrawalMethod $method, int $amount, array $details, string $formId): Withdrawal
    {
        if ($existing = Withdrawal::where('form_id', $formId)->first()) {
            return $existing; // the same form sent twice
        }
        if (! $method->is_active) {
            throw new InvalidArgumentException('That way of being paid is not available now.');
        }
        if ($amount < $method->min_amount || $amount > $method->max_amount) {
            throw new InvalidArgumentException(sprintf('Withdraw between ₦%s and ₦%s with %s.', number_format($method->min_amount / 100, 2), number_format($method->max_amount / 100, 2), $method->name));
        }
        $charge = $method->chargeOn($amount);
        if ($charge >= $amount) {
            throw new InvalidArgumentException('The amount must be more than the charge.');
        }

        $clean = [];
        foreach ($method->fields as $field) {
            $value = trim((string) ($details[$field['key']] ?? ''));
            if ($value === '') {
                throw new InvalidArgumentException("Fill in the {$field['label']}.");
            }
            $clean[$field['key']] = mb_substr($value, 0, 120);
        }

        return DB::transaction(function () use ($holderType, $holderId, $wallet, $method, $amount, $charge, $clean, $formId) {
            $withdrawal = Withdrawal::create([
                'holder_type' => $holderType, 'holder_id' => $holderId, 'wallet_account_id' => $wallet->id, 'method_id' => $method->id,
                'amount' => $amount, 'charge' => $charge, 'payable' => $amount - $charge, 'details' => $clean,
                'status' => Withdrawal::PENDING, 'form_id' => $formId,
            ]);

            // Refused (and nothing written) if the wallet is short.
            $this->ledger->transfer($wallet, $this->ledger->systemAccount('withdrawals.pending'), $amount, 'withdrawal.request', 'withdraw:'.$withdrawal->id, "Withdrawal #{$withdrawal->id} by {$method->name}", $withdrawal);

            return $withdrawal;
        });
    }

    /** The money has been sent: record it. */
    public function approve(Withdrawal $withdrawal, Admin $admin, string $reference): Withdrawal
    {
        $reference = trim($reference);
        if ($reference === '') {
            throw new InvalidArgumentException('Give the reference of the payment you sent.');
        }

        return DB::transaction(function () use ($withdrawal, $admin, $reference) {
            $withdrawal = $this->pending($withdrawal);

            $lines = [[$this->ledger->systemAccount('withdrawals.pending'), -$withdrawal->amount], [$this->ledger->systemAccount('external'), $withdrawal->payable]];
            if ($withdrawal->charge > 0) {
                $lines[] = [$this->ledger->cooperativeAccount('withdrawal_charges'), $withdrawal->charge];
            }
            $this->ledger->post('withdrawal.paid', 'withdraw:paid:'.$withdrawal->id, $lines, "Withdrawal #{$withdrawal->id} paid, ref. {$reference}", $withdrawal, $admin->id);

            $withdrawal->forceFill(['status' => Withdrawal::PAID, 'reference' => $reference, 'decided_by_admin_id' => $admin->id, 'decided_at' => now()])->save();
            $this->audit->record($admin, 'withdrawals.approve', $withdrawal, ['status' => Withdrawal::PENDING], ['status' => Withdrawal::PAID, 'payable' => $withdrawal->payable], 'Reference: '.$reference);
            $this->tell($withdrawal, 'withdrawal.paid', ['payable' => Messages::naira($withdrawal->payable), 'reference' => $reference]);

            return $withdrawal;
        });
    }

    /** Refused: the full amount, charge included, goes back to the wallet. */
    public function reject(Withdrawal $withdrawal, Admin $admin, string $reason): Withdrawal
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException('Give a reason. The person sees it.');
        }

        return DB::transaction(function () use ($withdrawal, $admin, $reason) {
            $withdrawal = $this->pending($withdrawal);

            $this->ledger->transfer($this->ledger->systemAccount('withdrawals.pending'), $withdrawal->wallet, $withdrawal->amount, 'withdrawal.refund', 'withdraw:refund:'.$withdrawal->id, "Withdrawal #{$withdrawal->id} refused: {$reason}", $withdrawal, $admin->id);

            $withdrawal->forceFill(['status' => Withdrawal::REJECTED, 'reject_reason' => mb_substr($reason, 0, 250), 'decided_by_admin_id' => $admin->id, 'decided_at' => now()])->save();
            $this->audit->record($admin, 'withdrawals.reject', $withdrawal, ['status' => Withdrawal::PENDING], ['status' => Withdrawal::REJECTED], $reason);
            $this->tell($withdrawal, 'withdrawal.rejected', ['reason' => $reason]);

            return $withdrawal;
        });
    }

    /** Tells the person who asked: the member, the store's owner, or the affiliate (section 12). */
    private function tell(Withdrawal $withdrawal, string $event, array $data): void
    {
        $data['amount'] = Messages::naira($withdrawal->amount);
        $messages = app(Messages::class);
        match ($withdrawal->holder_type) {
            Withdrawal::MEMBER => ($user = User::find($withdrawal->holder_id)) && $messages->send($user, $event, $data),
            Withdrawal::STORE => ($user = Store::find($withdrawal->holder_id)?->owner) && $messages->send($user, $event, $data),
            Withdrawal::AFFILIATE => ($affiliate = Affiliate::find($withdrawal->holder_id)) && $messages->sendTo($affiliate->email, null, $event, ['name' => $affiliate->first_name] + $data),
            default => null,
        };
    }

    private function pending(Withdrawal $withdrawal): Withdrawal
    {
        $locked = Withdrawal::with('wallet')->lockForUpdate()->findOrFail($withdrawal->id);
        if ($locked->status !== Withdrawal::PENDING) {
            throw new LedgerException('This withdrawal has already been decided.');
        }

        return $locked;
    }
}
