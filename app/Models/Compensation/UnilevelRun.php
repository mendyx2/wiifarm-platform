<?php

namespace App\Models\Compensation;

use App\Models\Pools\HeldPayout;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One month's unilevel bonus run (specification section 5.10).
 *
 * @property int $id
 * @property string $month YYYY-MM
 * @property string $status
 * @property bool $activity_required
 * @property int $repurchase_pv
 * @property int $earners
 * @property int $amount
 * @property int $unpaid
 * @property ?Carbon $finished_at
 */
class UnilevelRun extends Model
{
    public const RUNNING = 'running';

    public const DONE = 'done';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'activity_required' => 'boolean', 'repurchase_pv' => 'integer', 'earners' => 'integer',
            'amount' => 'integer', 'unpaid' => 'integer', 'finished_at' => 'datetime',
        ];
    }

    /** @return HasMany<UnilevelPayout, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(UnilevelPayout::class);
    }

    public function stillHeld(): int
    {
        return (int) HeldPayout::where('status', HeldPayout::HELD)->where('type', 'unilevel')
            ->where('idempotency_key', 'like', "unilevel:{$this->month}:%")->sum('amount');
    }
}
