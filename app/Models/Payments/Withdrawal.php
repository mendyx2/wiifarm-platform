<?php

namespace App\Models\Payments;

use App\Models\Ledger\Account;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A withdrawal request (specification section 6).
 *
 * @property int $id
 * @property string $holder_type
 * @property int $holder_id
 * @property int $wallet_account_id
 * @property int $method_id
 * @property int $amount
 * @property int $charge
 * @property int $payable
 * @property array<string, string> $details
 * @property string $status
 * @property string $form_id
 * @property ?string $reference
 * @property ?string $reject_reason
 * @property ?Carbon $decided_at
 */
class Withdrawal extends Model
{
    public const MEMBER = 'member';

    public const STORE = 'store';

    public const AFFILIATE = 'affiliate';

    public const PENDING = 'pending';

    public const PAID = 'paid';

    public const REJECTED = 'rejected';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'charge' => 'integer', 'payable' => 'integer', 'details' => 'array', 'decided_at' => 'datetime'];
    }

    /** @return BelongsTo<WithdrawalMethod, $this> */
    public function method(): BelongsTo
    {
        return $this->belongsTo(WithdrawalMethod::class);
    }

    /** @return BelongsTo<Account, $this> */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'wallet_account_id');
    }
}
