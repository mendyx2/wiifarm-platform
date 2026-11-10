<?php

namespace Database\Seeders;

use App\Models\Package;
use Illuminate\Database\Seeder;

/**
 * The seven packages (specification section 4). Adds any that are missing;
 * never changes a package that exists, because an admin may have changed it.
 */
class PackageSeeder extends Seeder
{
    /** name, price in naira, PV, packs, direct %, match %, daily cap (matches), unilevel generations */
    public const PACKAGES = [
        ['Pre Starter', 15_000, 15, 1, '25.00', '3.00', 5, 4],
        ['Starter', 30_000, 30, 2, '25.00', '5.00', 7, 6],
        ['Builder', 60_000, 60, 4, '25.00', '7.00', 10, 8],
        ['Booster', 105_000, 105, 7, '25.00', '8.00', 30, 10],
        ['Executive', 150_000, 150, 10, '25.00', '9.00', 60, 12],
        ['Advance', 255_000, 255, 17, '25.00', '10.00', 90, 13],
        ['Mega', 450_000, 450, 30, '25.00', '12.00', 120, 15],
    ];

    public function run(): void
    {
        foreach (self::PACKAGES as $i => [$name, $naira, $pv, $packs, $direct, $match, $cap, $generations]) {
            Package::firstOrCreate(['name' => $name], [
                'sort' => $i + 1,
                'price' => $naira * 100,
                'pv' => $pv,
                'product_packs' => $packs,
                'direct_bonus_percent' => $direct,
                'match_percent' => $match,
                'daily_cap_matches' => $cap,
                'unilevel_generations' => $generations,
            ]);
        }
    }
}
