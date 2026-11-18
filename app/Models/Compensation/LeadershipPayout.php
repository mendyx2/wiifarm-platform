<?php

namespace App\Models\Compensation;

use App\Models\Pools\HeldPayout;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What one leader earned in one month's leadership run.
 *
 * @property int $id
 * @property int $leadership_run_id
 * @property int $user_id
 * @property int $repurchase_pv
 * @property int $team_members
 * @property int $team_matching
 * @property int $amount
 * @property string $status
 */
class LeadershipPayout extends Model
{
    public const PAID = 'paid';

    public const HELD = 'held';

    /** Qualified, but the team earned no matching that month. */
    public const NONE = 'none';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['repurchase_pv' => 'integer', 'team_members' => 'integer', 'team_matching' => 'integer', 'amount' => 'integer'];
    }

    /** Paid, held, or nothing to pay; a held payout is paid later, when the line refills. */
    public function currentStatus(): string
    {
        if ($this->status !== self::HELD) {
            return $this->status;
        }

        return HeldPayout::where('status', HeldPayout::HELD)->where('idempotency_key', "leadership:{$this->run->month}:{$this->user_id}")->exists()
            ? self::HELD : self::PAID;
    }

    /** @return BelongsTo<LeadershipRun, $this> */
    public function run(): BelongsTo
    {
        return $this->belongsTo(LeadershipRun::class, 'leadership_run_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
