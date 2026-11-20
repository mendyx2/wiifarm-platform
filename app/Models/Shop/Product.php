<?php

namespace App\Models\Shop;

use App\Models\Pools\Manufacturer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A product (specification section 8). Each names its manufacturer, so the
 * manufacturer's share of a sale goes to the right partner's account.
 *
 * @property int $id
 * @property int $manufacturer_id
 * @property string $name
 * @property string $sku
 * @property ?string $description
 * @property ?string $image_path
 * @property int $member_price
 * @property int $public_price
 * @property int $pv
 * @property int $units_per_pack
 * @property ?int $category_id
 * @property bool $is_active
 */
class Product extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['member_price' => 'integer', 'public_price' => 'integer', 'pv' => 'integer', 'units_per_pack' => 'integer', 'is_active' => 'boolean'];
    }

    /** @return BelongsTo<Manufacturer, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(Manufacturer::class);
    }

    /** The photo's web address, or null when the product has none. */
    public function imageUrl(): ?string
    {
        return $this->image_path ? '/uploads/'.$this->image_path : null;
    }
}
