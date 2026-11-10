<?php

namespace App\Services\Network;

use App\Models\Ledger\Transaction;
use App\Models\User;
use App\Services\Ledger\Ledger;
use Illuminate\Database\Eloquent\Model;

/**
 * PV climbing the tree (specification section 3). When a member gains PV,
 * every upline receives it, all the way to the top, on the leg the member
 * sits under: as lifetime PV (for awards) and, unless the source counts for
 * awards only, as pairing PV (for matching).
 *
 * It is one ledger transaction in PV, so it is recorded with the member (the
 * subject), each upline's leg, the amount and the source (its type and key),
 * and the same source can never climb twice.
 */
class PvClimb
{
    public const SOURCE_REGISTRATION = 'pv.registration';

    public const SOURCE_UPGRADE = 'pv.upgrade';

    public const SOURCE_PURCHASE = 'pv.purchase';

    public const SOURCE_STORE = 'pv.store'; // awards only: lifetime PV, never pairing

    public function __construct(private readonly Tree $tree, private readonly Ledger $ledger) {}

    /**
     * @param  int  $pv  in hundredths of a PV (15 PV = 1500)
     * @return ?Transaction null when the member has no uplines (the top of the tree)
     */
    public function credit(User $member, int $pv, string $source, string $key, ?Model $subject = null): ?Transaction
    {
        if ($pv <= 0) {
            throw new PlacementException('PV to climb must be more than zero.');
        }

        $uplines = $this->tree->uplines($member);
        if ($uplines === []) {
            return null;
        }

        $pairing = $source !== self::SOURCE_STORE;
        $users = User::whereIn('id', array_column($uplines, 'id'))->get()->keyBy('id');
        $lines = [];

        foreach ($uplines as $upline) {
            $user = $users[$upline['id']];
            $lines[] = [$this->ledger->pvAccount($user, $upline['leg'], 'lifetime'), $pv];
            if ($pairing) {
                $lines[] = [$this->ledger->pvAccount($user, $upline['leg'], 'pairing'), $pv];
            }
        }
        $lines[] = [$this->ledger->systemAccount('pv.issued'), -$pv * count($lines)];

        return $this->ledger->post($source, $key, $lines, "PV from @{$member->username}", $subject ?? $member);
    }
}
