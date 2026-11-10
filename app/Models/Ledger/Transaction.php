<?php

namespace App\Models\Ledger;

use App\Models\Admin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One movement of money or PV: a set of entries that add up to zero.
 * Written once by App\Services\Ledger\Ledger::post(); never changed or deleted.
 *
 * @property int $id
 * @property string $type
 * @property string $idempotency_key
 * @property string $fingerprint
 * @property ?string $memo
 * @property ?string $subject_type
 * @property ?int $subject_id
 * @property ?int $admin_id
 * @property Carbon $created_at
 */
class Transaction extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'ledger_transactions';

    protected $guarded = ['id'];

    /** Set by Ledger::post() when the trigger had already been posted and nothing new was written. */
    public bool $wasAlreadyPosted = false;

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Ledger transactions cannot be changed.'));
        static::deleting(fn () => throw new \LogicException('Ledger transactions cannot be deleted.'));
    }

    /** @return HasMany<Entry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(Entry::class, 'transaction_id');
    }

    /** @return BelongsTo<Admin, $this> */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }
}
