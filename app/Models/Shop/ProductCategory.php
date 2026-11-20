<?php

namespace App\Models\Shop;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A group of products, such as "Nutrition" or "Wellness" (specification section 8).
 *
 * @property int $id
 * @property string $name
 * @property int $sort
 */
class ProductCategory extends Model
{
    protected $guarded = ['id'];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'category_id');
    }

    /** For the shops' filters: id and name, in the admin's order. */
    public static function forShop(): array
    {
        return self::orderBy('sort')->orderBy('name')->get(['id', 'name'])->toArray();
    }
}
