<?php

namespace App\Models\Payments;

use App\Models\Admin;
use App\Models\Package;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;

/**
 * An e-pin: a code worth a fixed amount that pays one registration, once.
 *
 * @property int $id
 * @property string $code_hash
 * @property string $code_encrypted
 * @property int $amount
 * @property ?int $package_id
 * @property string $status
 * @property ?int $created_by_user_id
 * @property ?int $created_by_admin_id
 * @property ?string $reason
 * @property string $batch
 * @property ?int $used_by_user_id
 * @property ?Carbon $used_at
 * @property ?int $cancelled_by_admin_id
 * @property ?string $cancel_reason
 * @property ?Carbon $cancelled_at
 * @property Carbon $created_at
 */
class EPin extends Model
{
    public const ACTIVE = 'active';

    public const USED = 'used';

    public const CANCELLED = 'cancelled';

    protected $table = 'epins';

    protected $guarded = ['id'];

    protected $hidden = ['code_hash', 'code_encrypted'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'used_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    /** The code itself, for the member who bought it or an admin. */
    public function code(): string
    {
        return Crypt::decryptString($this->code_encrypted);
    }

    /** @return BelongsTo<Package, $this> */
    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    /** @return BelongsTo<User, $this> */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return BelongsTo<Admin, $this> */
    public function creatorAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }

    /** @return BelongsTo<User, $this> */
    public function usedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'used_by_user_id');
    }
}
