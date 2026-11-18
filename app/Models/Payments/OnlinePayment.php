<?php

namespace App\Models\Payments;

use App\Models\Package;
use App\Models\Shop\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A payment made online through Paystack: a package, an upgrade or a deposit.
 *
 * @property int $id
 * @property ?int $user_id
 * @property ?int $order_id
 * @property string $purpose
 * @property ?int $package_id
 * @property ?int $from_package_id
 * @property int $amount
 * @property string $gateway
 * @property string $reference
 * @property string $status
 * @property ?string $gateway_status
 * @property ?string $channel
 * @property ?string $note
 * @property ?Carbon $paid_at
 * @property ?Carbon $checked_at
 * @property Carbon $created_at
 */
class OnlinePayment extends Model
{
    public const PACKAGE = 'package';

    public const UPGRADE = 'upgrade';

    public const DEPOSIT = 'deposit';

    /** A public-shop order (section 8): the buyer has no account. */
    public const PUBLIC_ORDER = 'public';

    public const PENDING = 'pending';

    public const PAID = 'paid';

    public const FAILED = 'failed';

    /** Paystack says it was paid, but not the amount or currency asked for: an admin must look. */
    public const MISMATCH = 'mismatch';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'paid_at' => 'datetime', 'checked_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<Package, $this> */
    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function describe(): string
    {
        return match ($this->purpose) {
            self::PACKAGE => 'The '.$this->package?->name.' package',
            self::UPGRADE => 'Upgrade to '.$this->package?->name,
            self::PUBLIC_ORDER => 'Public shop order '.$this->order?->number,
            default => 'Deposit to the main balance',
        };
    }
}
