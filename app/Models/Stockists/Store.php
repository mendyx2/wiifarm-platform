<?php

namespace App\Models\Stockists;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A stockist's store (specification section 9). A Mega store receives stock
 * from the manufacturer; a Sub store collects from its Mega store.
 *
 * @property int $id
 * @property int $user_id
 * @property string $kind
 * @property ?int $mega_store_id
 * @property int $sponsor_id
 * @property string $name
 * @property string $state
 * @property string $address
 * @property string $phone
 * @property string $status
 * @property int $deposit
 * @property ?string $deposit_reference
 * @property ?Carbon $activated_at
 */
class Store extends Model
{
    public const MEGA = 'mega';

    public const SUB = 'sub';

    public const PENDING = 'pending';

    public const ACTIVE = 'active';

    public const DEACTIVATED = 'deactivated';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['deposit' => 'integer', 'activated_at' => 'datetime'];
    }

    public function isActive(): bool
    {
        return $this->status === self::ACTIVE;
    }

    public function label(): string
    {
        return ($this->kind === self::MEGA ? 'Mega' : 'Sub').' store';
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function sponsor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sponsor_id');
    }

    /** @return BelongsTo<Store, $this> */
    public function mega(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'mega_store_id');
    }

    /** @return HasMany<Store, $this> */
    public function subs(): HasMany
    {
        return $this->hasMany(Store::class, 'mega_store_id');
    }
}
