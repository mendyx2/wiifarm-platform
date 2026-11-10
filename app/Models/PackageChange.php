<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A member joining with a package, or upgrading it. Written once.
 *
 * @property int $id
 * @property int $user_id
 * @property string $kind
 * @property ?int $from_package_id
 * @property int $to_package_id
 * @property string $payment_key
 */
class PackageChange extends Model
{
    public const UPDATED_AT = null;

    public const JOIN = 'join';

    public const UPGRADE = 'upgrade';

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('A package change cannot be edited.'));
        static::deleting(fn () => throw new \LogicException('A package change cannot be deleted.'));
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Package, $this> */
    public function fromPackage(): BelongsTo
    {
        return $this->belongsTo(Package::class, 'from_package_id');
    }

    /** @return BelongsTo<Package, $this> */
    public function toPackage(): BelongsTo
    {
        return $this->belongsTo(Package::class, 'to_package_id');
    }
}
