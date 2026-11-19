<?php

namespace App\Models\Compensation;

use App\Models\Admin;
use App\Models\Pools\HeldPayout;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A recognition (rank) award or a repurchase award tier (specification 5.8
 * and 5.11), earned once.
 *
 * @property int $id
 * @property int $user_id
 * @property string $kind
 * @property string $code
 * @property string $name
 * @property int $amount
 * @property ?string $gift
 * @property string $choice
 * @property int $measure
 * @property string $status
 * @property ?string $reason
 * @property ?int $decided_by_admin_id
 * @property ?Carbon $decided_at
 * @property ?Carbon $delivered_at
 * @property Carbon $created_at
 */
class Award extends Model
{
    public const RANK = 'rank';

    public const REPURCHASE = 'repurchase';

    public const PENDING = 'pending';     // waiting for an admin

    public const PAID = 'paid';           // cash in the member's wallet

    public const HELD = 'held';           // approved; waiting for its pool to refill

    public const GIFT = 'gift';           // approved as a gift, its value set aside; to be delivered

    public const DELIVERED = 'delivered'; // the gift was handed over

    public const REJECTED = 'rejected';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'measure' => 'integer', 'decided_at' => 'datetime', 'delivered_at' => 'datetime'];
    }

    /** The status now: a held award becomes paid (or a gift to deliver) once its pool has paid it. */
    public function currentStatus(): string
    {
        if ($this->status !== self::HELD) {
            return $this->status;
        }
        if (HeldPayout::where('status', HeldPayout::HELD)->where('idempotency_key', 'award:'.$this->id)->exists()) {
            return self::HELD;
        }

        return $this->choice === 'gift' ? self::GIFT : self::PAID;
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Admin, $this> */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'decided_by_admin_id');
    }
}
