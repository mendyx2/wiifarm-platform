<?php

namespace Database\Seeders;

use App\Models\Pools\AllocationSchedule;
use App\Models\Pools\Manufacturer;
use Illuminate\Database\Seeder;

/**
 * The first manufacturing partner and version 1 of the allocation schedule
 * (the board's Allocation Blueprint V3). Never changes what already exists.
 */
class PoolSeeder extends Seeder
{
    public function run(): void
    {
        Manufacturer::firstOrCreate(['name' => env('WIIFARM_MANUFACTURER_NAME', 'Manufacturing partner')]);

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
