<?php

namespace App\Models\Ledger;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One ledger account: a member's wallet, or one of the platform's accounts.
 * It holds no balance; App\Services\Ledger\Ledger::balance() adds up its entries.
 *
 * @property int $id
 * @property string $holder
 * @property ?int $user_id
 * @property string $kind
 * @property string $currency
 */
class Account extends Model
{
    public const UPDATED_AT = null;

    public const SYSTEM = 'system';

    protected $table = 'ledger_accounts';

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Ledger accounts cannot be changed.'));
        static::deleting(fn () => throw new \LogicException('Ledger accounts cannot be deleted.'));
    }

    public function isSystem(): bool
    {
        return $this->holder === self::SYSTEM;
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<Entry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(Entry::class, 'account_id');
    }
}
