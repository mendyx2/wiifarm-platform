<?php

namespace App\Models\Compensation;

use App\Models\Pools\HeldPayout;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What one member earned in one month's unilevel run, by generation.
 *
 * @property int $id
 * @property int $unilevel_run_id
 * @property int $user_id
 * @property array<int, int> $by_generation
 * @property int $amount
 * @property string $status
 */
class UnilevelPayout extends Model
{
    public const PAID = 'paid';

    public const HELD = 'held';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['by_generation' => 'array', 'amount' => 'integer'];
    }

    public function currentStatus(): string
    {
        if ($this->status === self::PAID) {
            return self::PAID;
        }

        return HeldPayout::where('status', HeldPayout::HELD)->where('idempotency_key', "unilevel:{$this->run->month}:{$this->user_id}")->exists()
            ? self::HELD : self::PAID;
    }

    /** @return BelongsTo<UnilevelRun, $this> */
    public function run(): BelongsTo
    {
        return $this->belongsTo(UnilevelRun::class, 'unilevel_run_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
