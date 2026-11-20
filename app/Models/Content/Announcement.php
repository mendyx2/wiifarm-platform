<?php

namespace App\Models\Content;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A notice from Wiifarm to all members, shown on their dashboard while it
 * runs (specification section 11).
 *
 * @property int $id
 * @property string $title
 * @property string $body
 * @property string $tone
 * @property ?Carbon $starts_at
 * @property ?Carbon $ends_at
 * @property bool $is_active
 */
class Announcement extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'is_active' => 'boolean'];
    }

    /** Switched on, started and not ended. */
    public function scopeShowing(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }
}
