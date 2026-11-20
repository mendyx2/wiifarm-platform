<?php

namespace App\Models\Payments;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A deposit by a manual method, waiting for or decided by an admin
 * (specification section 6).
 *
 * @property int $id
 * @property int $user_id
 * @property int $method_id
 * @property int $amount
 * @property string $reference
 * @property string $proof_path
 * @property string $status
 * @property ?string $reject_reason
 * @property ?Carbon $decided_at
 * @property ?int $transaction_id
 * @property Carbon $created_at
 */
class ManualDeposit extends Model
{
    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime', 'amount' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function method(): BelongsTo
    {
        return $this->belongsTo(DepositMethod::class, 'method_id');
    }
}
