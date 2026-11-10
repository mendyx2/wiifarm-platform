<?php

namespace App\Models\Ledger;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of a transaction: an amount into (positive) or out of (negative)
 * one account. Never changed or deleted.
 *
 * @property int $id
 * @property int $transaction_id
 * @property int $account_id
 * @property int $amount
 */
class Entry extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'ledger_entries';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Ledger entries cannot be changed.'));
        static::deleting(fn () => throw new \LogicException('Ledger entries cannot be deleted.'));
    }

    /** @return BelongsTo<Transaction, $this> */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }
}
