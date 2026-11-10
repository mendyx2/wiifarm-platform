<?php

namespace App\Services\Admin;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * An admin bans or unbans a member, or blocks or unblocks their withdrawals
 * (specification section 2). Each change is recorded in the audit log, in
 * the same database transaction as the change itself.
 */
class MemberStanding
{
    public function __construct(private readonly Audit $audit) {}

    public function ban(Admin $admin, User $member, string $reason): void
    {
        $reason = $this->reason($reason, 'Give a reason. The member sees it when they try to sign in.');

        if ($member->is(User::company())) {
            throw new InvalidArgumentException('The company account cannot be banned.');
        }
        if ($member->isBanned()) {
            throw new InvalidArgumentException('This member is already banned.');
        }

        $this->change($admin, $member, 'members.ban', ['status' => User::STATUS_BANNED, 'ban_reason' => $reason], $reason);
    }

    public function unban(Admin $admin, User $member, string $note): void
    {
        if (! $member->isBanned()) {
            throw new InvalidArgumentException('This member is not banned.');
        }

        $this->change($admin, $member, 'members.unban', ['status' => User::STATUS_ACTIVE, 'ban_reason' => null], $this->reason($note, 'Say why the ban is lifted.'));
    }

    public function blockWithdrawals(Admin $admin, User $member, string $reason): void
    {
        $reason = $this->reason($reason, 'Give a reason. The member sees it when they try to withdraw.');

        if ($member->withdrawals_blocked) {
            throw new InvalidArgumentException('Withdrawals are already blocked for this member.');
        }

        $this->change($admin, $member, 'members.block_withdrawals', ['withdrawals_blocked' => true, 'withdrawals_block_reason' => $reason], $reason);
    }

    public function unblockWithdrawals(Admin $admin, User $member, string $note): void
    {
        if (! $member->withdrawals_blocked) {
            throw new InvalidArgumentException('Withdrawals are not blocked for this member.');
        }

        $this->change($admin, $member, 'members.unblock_withdrawals', ['withdrawals_blocked' => false, 'withdrawals_block_reason' => null], $this->reason($note, 'Say why withdrawals are opened again.'));
    }

    private function reason(string $text, string $message): string
    {
        $text = trim($text);

        if ($text === '') {
            throw new InvalidArgumentException($message);
        }

        return mb_substr($text, 0, 250);
    }

    /** @param  array<string, mixed>  $new */
    private function change(Admin $admin, User $member, string $action, array $new, string $note): void
    {
        DB::transaction(function () use ($admin, $member, $action, $new, $note) {
            $locked = User::lockForUpdate()->findOrFail($member->id);
            $old = $locked->only(array_keys($new)); // with casts applied, e.g. true/false rather than 1/0

            $locked->forceFill($new)->save();
            $this->audit->record($admin, $action, $locked, $old, $new, $note);
        });

        $member->refresh();
    }
}
