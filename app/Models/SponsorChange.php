<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One change of a member's sponsor. Written once, never edited.
 */
class SponsorChange extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function oldSponsor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'old_sponsor_id');
    }

    /** @return BelongsTo<User, $this> */
    public function newSponsor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'new_sponsor_id');
    }
}
