<?php

namespace App\Services\Members;

use App\Models\SponsorChange;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Moves a member under a different sponsor (specification section 2: only an
 * admin may do this, and every change is logged with the old and new sponsor).
 *
 * The admin screen arrives in phase 2d; the rules live here so every caller
 * shares them.
 */
class ChangeSponsor
{
    /**
     * @throws InvalidArgumentException when the change is not allowed
     */
    public function __invoke(User $member, User $newSponsor, string $reason, ?int $adminId = null): SponsorChange
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new InvalidArgumentException('Give a reason for the change.');
        }
        if ($member->is(User::company())) {
            throw new InvalidArgumentException('The company account has no sponsor.');
        }
        if ($member->is($newSponsor)) {
            throw new InvalidArgumentException('A member cannot sponsor themselves.');
        }
        if ($member->sponsor_id === $newSponsor->id) {
            throw new InvalidArgumentException('That member is already the sponsor.');
        }
        if ($newSponsor->isBanned()) {
            throw new InvalidArgumentException('A banned member cannot be a sponsor.');
        }
        if ($this->isInReferralLine($newSponsor, $member)) {
            throw new InvalidArgumentException('The new sponsor is in this member\'s own downline; the change would make a loop.');
        }

        return DB::transaction(function () use ($member, $newSponsor, $reason, $adminId) {
            $locked = User::lockForUpdate()->findOrFail($member->id);

            $change = SponsorChange::create([
                'user_id' => $locked->id,
                'old_sponsor_id' => $locked->sponsor_id,
                'new_sponsor_id' => $newSponsor->id,
                'changed_by_admin_id' => $adminId,
                'reason' => $reason,
            ]);

            $locked->sponsor_id = $newSponsor->id;
            $locked->save();

            return $change;
        });
    }

    /**
     * Whether $ancestor appears above $candidate in the referral chain, i.e.
     * $candidate is in $ancestor's downline.
     */
    private function isInReferralLine(User $candidate, User $ancestor): bool
    {
        $seen = [];
        $current = $candidate->sponsor_id;

        while ($current !== null && ! isset($seen[$current])) {
            if ($current === $ancestor->id) {
                return true;
            }
            $seen[$current] = true;
            $current = User::whereKey($current)->value('sponsor_id');
        }

        return false;
    }
}
