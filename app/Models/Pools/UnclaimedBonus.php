<?php

namespace App\Models\Pools;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One inflow into the Unclaimed Bonus Reserve: a bonus share nobody could
 * receive, and where it came from.
 *
 * @property int $id
 * @property string $pool
 * @property string $line
 * @property string $bonus
 * @property ?int $generation
 * @property string $reason
 * @property ?int $beneficiary_user_id
 * @property ?int $source_user_id
 * @property ?string $subject_type
 * @property ?int $subject_id
 * @property int $amount
 * @property int $transaction_id
 * @property ?string $memo
 * @property Carbon $created_at
 */
class UnclaimedBonus extends Model
{
    public const UPDATED_AT = null;

    /** Why a share could not be paid, in plain words. */
    public const REASONS = [
        'no_upline' => 'No upline at that generation',
        'company' => 'The company account',
        'banned' => 'The upline is banned',
        'not_paid' => "The upline's package is not paid",
        'package_depth' => "The upline's package does not reach that generation",
        'no_key_in' => 'Nobody keyed the member in (self sign-up)',
        'autoship_missed' => 'Autoship not made for 3 months in a row',
    ];

    public const BONUSES = [
        'direct' => 'Direct referral bonus',
        'indirect' => 'Indirect referral bonus',
        'keyin' => 'Key-in bonus',
        'upgrade' => 'Upgrade bonus',
        'unilevel' => 'Unilevel bonus',
        'stockist_sponsor' => 'Stockist sponsor bonus',
        'ucb' => 'Upline Celebrated Bonus',
        'autoship' => 'Matching held for autoship',
    ];

    protected $table = 'unclaimed_bonuses';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'generation' => 'integer', 'created_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(User::class, 'beneficiary_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function source(): BelongsTo
    {
        return $this->belongsTo(User::class, 'source_user_id');
    }

    /** Why someone cannot receive a bonus share, or null if they can. */
    public static function reasonFor(?User $member, bool $packageReaches = true): ?string
    {
        return match (true) {
            $member === null => 'no_upline',
            $member->is(User::company()) => 'company',
            $member->isBanned() => 'banned',
            ! $member->isActivated() => 'not_paid',
            ! $packageReaches => 'package_depth',
            default => null,
        };
    }
}
