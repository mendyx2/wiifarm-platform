<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A member's place in the binary tree (specification section 3). Written once,
 * when the member is placed.
 *
 * @property int $user_id
 * @property ?int $parent_id
 * @property ?string $leg
 * @property int $depth
 */
class TreePosition extends Model
{
    public const LEFT = 'L';

    public const RIGHT = 'R';

    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = 'user_id';

    protected $guarded = [];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('A place in the tree cannot be changed.'));
        static::deleting(fn () => throw new \LogicException('A place in the tree cannot be removed.'));
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'parent_id');
    }
}
