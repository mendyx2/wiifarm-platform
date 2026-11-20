<?php

namespace App\Models\Payments;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A USDT deposit: the exact amount a member was asked to send to Wiifarm's
 * address, and what happened (specification section 6).
 *
 * @property int $id
 * @property int $user_id
 * @property int $amount
 * @property int $rate_kobo
 * @property int $units
 * @property string $address
 * @property string $status
 * @property Carbon $expires_at
 * @property ?string $txid
 * @property ?int $received_units
 * @property ?string $sender
 * @property ?Carbon $sent_at
 * @property ?int $credited
 * @property ?string $note
 * @property Carbon $created_at
 */
class UsdtDeposit extends Model
{
    public const WAITING = 'waiting';

    public const PAID = 'paid';

    public const EXPIRED = 'expired';

    public const REVIEW = 'review';

    public const REJECTED = 'rejected';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'sent_at' => 'datetime', 'amount' => 'integer', 'units' => 'integer', 'received_units' => 'integer', 'rate_kobo' => 'integer', 'credited' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** "65.280000" USDT as people read it: 65.28. */
    public static function usdt(int $units): string
    {
        return rtrim(rtrim(number_format($units / 1_000_000, 6, '.', ''), '0'), '.');
    }
}
