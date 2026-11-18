<?php

namespace App\Models\Pools;

use App\Models\Ledger\Account;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A payout its pool line could not cover yet (specification section 7: hold
 * until it refills). Paid in full once the money arrives, oldest first.
 *
 * @property int $id
 * @property string $pool
 * @property string $line
 * @property int $to_account_id
 * @property int $amount
 * @property string $type
 * @property string $idempotency_key
 * @property ?string $memo
 * @property string $status
 * @property ?int $transaction_id
 * @property Carbon $created_at
 * @property ?Carbon $paid_at
 */
class HeldPayout extends Model
{
    public const HELD = 'held';

    public const PAID = 'paid';

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'created_at' => 'datetime', 'paid_at' => 'datetime'];
    }

    /** @return BelongsTo<Account, $this> */
    public function toAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'to_account_id');
    }
}
