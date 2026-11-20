<?php

namespace App\Services\Messages;

use App\Models\Ledger\Account;
use App\Models\User;
use App\Notifications\InAppNotice;

/**
 * Notices on the member's dashboard (the bell). Bonuses are told here only,
 * never by email or SMS (the owner, 20 November 2026).
 */
class Notices
{
    /** A bonus paid into one of a member's bonus wallets. */
    public static function bonus(Account $to, int $amount, ?string $memo): void
    {
        if ($to->user_id === null || ! str_starts_with($to->kind, 'bonus.') || $amount <= 0) {
            return;
        }
        $member = User::find($to->user_id);
        $wallet = config('ledger.member_wallets')[$to->kind] ?? 'Bonus';
        $member?->notify(new InAppNotice('bonus', $wallet, $memo, $amount, route('wallet')));
    }
}
