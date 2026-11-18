<?php

namespace App\Services\Payments;

use App\Models\User;
use App\Services\Ledger\Ledger;
use App\Services\Ledger\LedgerException;

/**
 * Bonus transfer (specification section 6): a member moves money from one of
 * their bonus wallets to their own main balance, where it can be withdrawn or
 * spent. The form's one-time id makes a resent form count once.
 */
class BonusTransfer
{
    public function __construct(private readonly Ledger $ledger) {}

    public function move(User $member, string $bonusWallet, int $amount, string $formId): void
    {
        if (! str_starts_with($bonusWallet, 'bonus.') || ! array_key_exists($bonusWallet, config('ledger.member_wallets'))) {
            throw new LedgerException('Choose one of your bonus wallets.');
        }
        if ($amount <= 0) {
            throw new LedgerException('The amount must be more than zero.');
        }

        $this->ledger->transfer($this->ledger->memberAccount($member, $bonusWallet), $this->ledger->memberAccount($member, 'main'), $amount, 'bonus.transfer', 'bonus-transfer:'.$formId, 'Bonus transfer: '.config('ledger.member_wallets')[$bonusWallet], $member);
    }
}
