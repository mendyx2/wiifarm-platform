<?php

namespace App\Models\Payments;

use Illuminate\Database\Eloquent\Model;

/**
 * A manual way to deposit, such as a bank transfer (specification section 6).
 *
 * @property int $id
 * @property string $name
 * @property string $instructions
 * @property int $min_amount
 * @property int $max_amount
 * @property bool $is_active
 */
class DepositMethod extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'min_amount' => 'integer', 'max_amount' => 'integer'];
    }
}
