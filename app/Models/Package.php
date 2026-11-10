<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    /** @return HasMany<User, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** One match is 50 PV on each leg; its value is 50 × ₦600 = ₦30,000 (section 4). */
    public const MATCH_PV = 50;

    /** What one match pays this package, in kobo: its match percentage of one match's value. */
    public function perMatch(): int
    {
        return Money::share(self::MATCH_PV * self::PV_VALUE_KOBO, $this->match_percent);
    }

    /** The package's PV value in kobo: what its bonuses are worked out on. */
    public function pvValue(): int
    {
        return $this->pv * self::PV_VALUE_KOBO;
    }
}
