<?php

namespace App\Services\Admin;

use App\Models\Admin;
use App\Models\User;
use App\Services\Ledger\Ledger;
use App\Services\Ledger\LedgerException;
use Illuminate\Support\Facades\DB;

/**
 * An admin adds to or subtracts from a member's wallet, with a reason
 * (specification section 11, "Members"). It is an ordinary ledger posting
 * against the platform's "Admin corrections" account, never an edit of a
 * balance, and it is written to the audit log in the same transaction.
 *
 * The form sends a one-time id, so a double click or a resent form posts once.
 */
class WalletAdjustment
{
    public function __construct(private readonly Ledger $ledger, private readonly Audit $audit) {}

    public function apply(Admin $admin, User $member, string $wallet, bool $add, int $kobo, string $reason, string $formId): bool
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new LedgerException('Give a reason. It is shown on the member\'s statement.');
        }
        if ($kobo <= 0) {
            throw new LedgerException('The amount must be more than zero.');
        }

        return DB::transaction(function () use ($admin, $member, $wallet, $add, $kobo, $reason, $formId) {
            $account = $this->ledger->memberAccount($member, $wallet);
            $corrections = $this->ledger->systemAccount('adjustments');
            $before = $this->ledger->balance($account);

            $transaction = $add
                ? $this->ledger->transfer($corrections, $account, $kobo, 'adjustment.credit', 'adjust:'.$formId, $reason, $member, $admin->id)
                : $this->ledger->transfer($account, $corrections, $kobo, 'adjustment.debit', 'adjust:'.$formId, $reason, $member, $admin->id);

            if ($transaction->wasAlreadyPosted) {
                return false; // the same form sent twice: nothing more to do
            }

            $this->audit->record($admin, $add ? 'members.wallet_add' : 'members.wallet_subtract', $member,
                ['wallet' => $wallet, 'balance' => $before],
                ['wallet' => $wallet, 'balance' => $before + ($add ? $kobo : -$kobo), 'amount' => $kobo, 'transaction' => $transaction->id],
                $reason);

            return true;
        });
    }
}
