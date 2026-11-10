<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A package a member holds (specification section 4). Its price is in kobo;
 * its PV value (what bonuses are worked out on) is PV × ₦600.
 *
 * @property int $id
 * @property string $name
 * @property int $sort
 * @property int $price
 * @property int $pv
 * @property int $product_packs
 * @property string $direct_bonus_percent
 * @property string $match_percent
 * @property int $daily_cap_matches
 * @property int $unilevel_generations
 * @property bool $upgradable
 * @property bool $is_active
 */
class Package extends Model
{
    /** 1 PV = ₦600 for bonus calculations (section 1), in kobo. */
    public const PV_VALUE_KOBO = 60_000;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['price' => 'integer', 'pv' => 'integer', 'upgradable' => 'boolean', 'is_active' => 'boolean'];
    }

    /** The package's PV value in kobo: what its bonuses are worked out on. */
    public function pvValue(): int
    {
        return $this->pv * self::PV_VALUE_KOBO;
    }
}
