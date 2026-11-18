<?php

namespace App\Models\Compensation;

use App\Models\Pools\HeldPayout;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One month's leadership bonus run (specification section 5.7).
 *
 * @property int $id
 * @property string $month YYYY-MM
 * @property string $status
 * @property string $team_rule
 * @property int $leaders
 * @property int $amount
 * @property ?Carbon $finished_at
 */
class LeadershipRun extends Model
{
    public const RUNNING = 'running';

    public const DONE = 'done';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['leaders' => 'integer', 'amount' => 'integer', 'finished_at' => 'datetime'];
    }

    /** @return HasMany<LeadershipPayout, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(LeadershipPayout::class);
    }

    /** What this run's payouts still wait for, in kobo, now. */
    public function stillHeld(): int
    {
        return (int) HeldPayout::where('status', HeldPayout::HELD)->where('type', 'leadership')
            ->where('idempotency_key', 'like', "leadership:{$this->month}:%")->sum('amount');
    }
}
