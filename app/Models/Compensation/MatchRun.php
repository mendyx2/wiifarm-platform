<?php

namespace App\Models\Compensation;

use App\Models\Pools\HeldPayout;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One night's matching run (specification section 5.5).
 *
 * @property int $id
 * @property string $day YYYY-MM-DD
 * @property string $status
 * @property int $members
 * @property int $matches
 * @property int $amount
 * @property int $held
 * @property ?Carbon $started_at
 * @property ?Carbon $finished_at
 */
class MatchRun extends Model
{
    public const RUNNING = 'running';

    public const DONE = 'done';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'members' => 'integer', 'matches' => 'integer', 'amount' => 'integer', 'held' => 'integer',
            'started_at' => 'datetime', 'finished_at' => 'datetime',
        ];
    }

    /** What this run's payouts still wait for, in kobo, now (held payouts are paid as money arrives). */
    public function stillHeld(): int
    {
        return (int) HeldPayout::where('status', HeldPayout::HELD)->where('type', 'matching')
            ->where('idempotency_key', 'like', "matching:{$this->day}:%")->sum('amount');
    }

    /** @return HasMany<MemberMatch, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(MemberMatch::class);
    }
}
