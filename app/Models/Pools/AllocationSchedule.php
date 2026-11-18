<?php

namespace App\Models\Pools;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One version of the board's allocation schedule. Never changed once written:
 * a change is a new version that applies from its date onwards.
 *
 * @property int $id
 * @property int $version
 * @property array<string, mixed> $rates
 * @property string $source
 * @property Carbon $effective_from
 */
class AllocationSchedule extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['rates' => 'array', 'effective_from' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('A schedule version cannot be changed; add a new version.'));
        static::deleting(fn () => throw new \LogicException('A schedule version cannot be deleted.'));
    }

    /** The version in force now. */
    public static function current(): self
    {
        return static::where('effective_from', '<=', now())->orderByDesc('version')->first()
            ?? throw new \RuntimeException('No allocation schedule is set up. Run the database seeder.');
    }
}
