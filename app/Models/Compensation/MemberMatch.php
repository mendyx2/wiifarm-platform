<?php

namespace App\Models\Compensation;

use App\Models\Package;
use App\Models\Pools\HeldPayout;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What one member earned in one night's matching run.
 *
 * @property int $id
 * @property int $match_run_id
 * @property int $user_id
 * @property int $package_id
 * @property int $left_pv
 * @property int $right_pv
 * @property int $matches
 * @property bool $capped
 * @property int $amount
 * @property int $autoship
 * @property string $status
 */
class MemberMatch extends Model
{
    public const PAID = 'paid';

    public const HELD = 'held';

    protected $table = 'matches';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'left_pv' => 'integer', 'right_pv' => 'integer', 'matches' => 'integer', 'capped' => 'boolean',
            'amount' => 'integer', 'autoship' => 'integer',
        ];
    }

    /** Paid, or still waiting for the matching line to refill (a held payout is paid later, oldest first). */
    public function currentStatus(): string
    {
        if ($this->status === self::PAID) {
            return self::PAID;
        }

        return HeldPayout::where('status', HeldPayout::HELD)->where('idempotency_key', 'like', "matching:{$this->run->day}:{$this->user_id}%")->exists()
            ? self::HELD : self::PAID;
    }

    /** @return BelongsTo<MatchRun, $this> */
    public function run(): BelongsTo
    {
        return $this->belongsTo(MatchRun::class, 'match_run_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Package, $this> */
    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }
}
