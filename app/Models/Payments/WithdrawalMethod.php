<?php

namespace App\Models\Payments;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;

/**
 * A way to be paid out, with its limits, charges and form (specification section 6).
 *
 * @property int $id
 * @property string $name
 * @property int $min_amount
 * @property int $max_amount
 * @property int $fixed_charge
 * @property string $percent_charge
 * @property list<array{key: string, label: string}> $fields
 * @property bool $is_active
 */
class WithdrawalMethod extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['min_amount' => 'integer', 'max_amount' => 'integer', 'fixed_charge' => 'integer', 'fields' => 'array', 'is_active' => 'boolean'];
    }

    /** The charge on an amount: the fixed charge plus the percentage, in kobo. */
    public function chargeOn(int $amount): int
    {
        return $this->fixed_charge + Money::share($amount, (string) $this->percent_charge);
    }
}
