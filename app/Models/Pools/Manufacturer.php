<?php

namespace App\Models\Pools;

use Illuminate\Database\Eloquent\Model;

/**
 * A manufacturing partner: a separate company from the cooperative, with its
 * own account in the ledger (specification section 7).
 *
 * @property int $id
 * @property string $name
 * @property bool $is_active
 */
class Manufacturer extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
