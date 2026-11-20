<?php

namespace App\Services\Payments;

use App\Models\User;
use App\Services\Ledger\Ledger;
use App\Services\Messages\Messages;
use App\Support\Money;
use App\Support\Settings;
use InvalidArgumentException;

/**
 * Balance transfer (specification section 6): a member sends money from
 * their main balance to another member's, with a fixed and a percentage
 * charge set by the admin. As with withdrawals, the charge comes out of the
 * amount sent and goes to the cooperative; the other member receives the
 * rest. The form's one-time id makes a resent form count once.
 */
class BalanceTransfers
{
    public function __construct(private readonly Ledger $ledger) {}

    /** @return array{charge: int, receives: int} */
    public static function quote(int $amount): array
    {
        $charge = min($amount, (int) Settings::get('transfers.fixed_charge') + Money::share($amount, (string) Settings::get('transfers.percent_charge')));

        return ['charge' => $charge, 'receives' => $amount - $charge];
    }

    /** The member a username belongs to, if they can receive a transfer from this sender. */
    public static function recipient(User $sender, string $username): User
    {
        $to = User::where('username', trim(ltrim($username, '@')))->first();
        if ($to === null || ! $to->isActivated() || $to->is(User::company())) {
            throw new InvalidArgumentException('There is no member with this username.');
        }
        if ($to->is($sender)) {
            throw new InvalidArgumentException('You cannot send money to yourself.');
        }
        if ($to->isBanned()) {
            throw new InvalidArgumentException('This member cannot receive transfers.');
        }

        return $to;
    }

    public function send(User $sender, string $username, int $amount, string $formId): array
    {
        if (! Settings::get('transfers.enabled')) {
            throw new InvalidArgumentException('Balance transfers are switched off at the moment.');
        }
        if ($sender->withdrawals_blocked) {
            throw new InvalidArgumentException('Transfers are blocked on your account. Please contact support.');
        }
        [$min, $max] = [(int) Settings::get('transfers.min'), (int) Settings::get('transfers.max')];
        if ($amount < $min || $amount > $max) {
            throw new InvalidArgumentException(sprintf('Send between %s and %s.', Messages::naira($min), Messages::naira($max)));
        }
        $to = self::recipient($sender, $username);
        ['charge' => $charge, 'receives' => $receives] = self::quote($amount);
        if ($receives <= 0) {
            throw new InvalidArgumentException('After the charge, nothing would be left to send. Send a larger amount.');
        }

        $lines = [[$this->ledger->memberAccount($sender, 'main'), -$amount], [$this->ledger->memberAccount($to, 'main'), $receives]];
        if ($charge > 0) {
            $lines[] = [$this->ledger->cooperativeAccount('transfer_charges'), $charge];
        }
        // Refused, with nothing written, if the main balance is short.
        $tx = $this->ledger->post('balance.transfer', "balance-transfer:{$sender->id}:{$formId}", $lines, "Balance transfer from @{$sender->username} to @{$to->username}", $sender);
        if (! $tx->wasAlreadyPosted) {
            app(Messages::class)->send($to, 'transfer.received', ['amount' => Messages::naira($receives), 'sender' => '@'.$sender->username]);
        }

        return ['to' => $to, 'charge' => $charge, 'receives' => $receives];
    }
}
