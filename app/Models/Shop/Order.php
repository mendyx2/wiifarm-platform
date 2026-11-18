<?php

namespace App\Models\Shop;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An order (specification section 8). Once paid it carries a pickup code: it
 * works at any stockist, never expires, releases the whole order, and works once.
 *
 * @property int $id
 * @property ?string $number
 * @property string $kind
 * @property ?int $user_id
 * @property string $status
 * @property int $amount
 * @property int $pv
 * @property int $units
 * @property ?string $paid_with
 * @property ?string $payment_key
 * @property ?string $pickup_code_hash
 * @property ?string $pickup_code
 * @property ?string $pickup_code_hint
 * @property ?Carbon $paid_at
 * @property ?Carbon $fulfilled_at
 */
class Order extends Model
{
    public const REGISTRATION = 'registration';

    public const REPURCHASE = 'repurchase';

    public const PUBLIC = 'public';

    public const PENDING = 'pending';

    public const PAID = 'paid';

    public const FULFILLED = 'fulfilled';

    public const CANCELLED = 'cancelled';

    protected $guarded = ['id'];

    protected $hidden = ['pickup_code', 'pickup_code_hash'];

    protected function casts(): array
    {
        return [
            'amount' => 'integer', 'pv' => 'integer', 'units' => 'integer',
            'pickup_code' => 'encrypted',
            'paid_at' => 'datetime', 'fulfilled_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
