<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A problem found in the tree, for an admin to look at (specification section 3).
 *
 * @property int $id
 * @property string $kind
 * @property ?int $user_id
 * @property string $details
 */
class NetworkAlert extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }
}
