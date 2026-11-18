<?php

namespace Database\Seeders;

use App\Models\Pools\AllocationSchedule;
use App\Models\Pools\Manufacturer;
use App\Models\Shop\Product;
use Illuminate\Database\Seeder;

/**
 * The first manufacturing partner, the registration pack product, and version 1 of the allocation schedule
 * (the board's Allocation Blueprint V3). Never changes what already exists.
 */
class PoolSeeder extends Seeder
{
    public function run(): void
    {
        $maker = Manufacturer::firstOrCreate(['name' => env('WIIFARM_MANUFACTURER_NAME', 'Manufacturing partner')]);

        // The product in every package's packs (specification section 4). Rename it in the admin panel.
        Product::firstOrCreate(['sku' => config('wiifarm.registration_product_sku')], [
            'manufacturer_id' => $maker->id,
            'name' => 'Wiifarm product',
            'member_price' => 150_000,
            'public_price' => 200_000,
            'pv' => 150,
            'units_per_pack' => 10,
        ]);

        if (! AllocationSchedule::exists()) {
            AllocationSchedule::create([
                'version' => 1,
                'rates' => config('pools.schedule'),
                'source' => 'Allocation Blueprint V3 (board, November 2026)',
                'effective_from' => now(),
            ]);
        }
    }
}
