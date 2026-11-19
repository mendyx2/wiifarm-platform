<?php

/*
 * Awards (specification 5.8, 5.9 and 5.11; the plan document "Wiifarm Coop
 * Binary System" and the Revised Baseline Rules, 19 November 2026).
 * PV is whole PV; money is kobo.
 */
return [
    /*
     * Recognition (binary) ranks, lowest first. A member earns each rank once,
     * when the lifetime PV on their lesser leg reaches it, their package is at
     * least "package", and (from Gold up) they have produced the rank in
     * "needs" on both their left and their right genealogy.
     */
    'ranks' => [
        'emerald' => ['name' => 'Emerald', 'pv' => 1_500, 'cash' => 8_000_000, 'gift' => null, 'package' => 'Builder', 'needs' => null],
        'silver' => ['name' => 'Silver', 'pv' => 3_000, 'cash' => 15_000_000, 'gift' => null, 'package' => 'Booster', 'needs' => null],
        'jade' => ['name' => 'Jade', 'pv' => 6_000, 'cash' => 30_000_000, 'gift' => null, 'package' => 'Executive', 'needs' => null],
        'gold' => ['name' => 'Gold', 'pv' => 12_500, 'cash' => 55_000_000, 'gift' => 'Laptop', 'package' => 'Mega', 'needs' => 'jade'],
        'diamond' => ['name' => 'Diamond', 'pv' => 28_000, 'cash' => 120_000_000, 'gift' => 'Smart TV', 'package' => 'Mega', 'needs' => 'gold'],
        'executive_diamond' => ['name' => 'Executive Diamond', 'pv' => 40_000, 'cash' => 150_000_000, 'gift' => 'Solar freezer and washing machine', 'package' => 'Mega', 'needs' => 'diamond'],
        'platinum' => ['name' => 'Platinum', 'pv' => 85_000, 'cash' => 400_000_000, 'gift' => 'Complete solar system', 'package' => 'Mega', 'needs' => 'executive_diamond'],
        'premium_platinum' => ['name' => 'Premium Platinum', 'pv' => 150_000, 'cash' => 1_000_000_000, 'gift' => 'Small car', 'package' => 'Mega', 'needs' => 'platinum'],
        'ambassador' => ['name' => 'Ambassador', 'pv' => 350_000, 'cash' => 1_600_000_000, 'gift' => 'International trip', 'package' => 'Mega', 'needs' => 'premium_platinum'],
        'executive_ambassador' => ['name' => 'Executive Ambassador', 'pv' => 480_000, 'cash' => 2_500_000_000, 'gift' => 'Big car', 'package' => 'Mega', 'needs' => 'ambassador'],
        'sapphire' => ['name' => 'Sapphire', 'pv' => 800_000, 'cash' => 4_000_000_000, 'gift' => 'Land', 'package' => 'Mega', 'needs' => 'executive_ambassador'],
        'executive_sapphire' => ['name' => 'Executive Sapphire', 'pv' => 2_500_000, 'cash' => 10_000_000_000, 'gift' => 'Small bungalow', 'package' => 'Mega', 'needs' => 'sapphire'],
        'global_mega' => ['name' => 'Global Mega', 'pv' => 5_000_000, 'cash' => 18_000_000_000, 'gift' => 'Duplex', 'package' => 'Mega', 'needs' => 'executive_sapphire'],
    ],

    // Upline Celebrated Bonus: shares of an approved recognition award to the
    // 1st, 2nd and 3rd upline in the referral chain.
    'ucb' => ['5.00', '2.00', '1.00'],

    /*
     * Repurchase awards: the cumulative repurchase PV of a member's 1st to
     * 10th generations => the cumulative award. Each new tier pays the
     * difference from the last one paid.
     */
    'repurchase_generations' => 10,
    'repurchase_tiers' => [
        5_000 => 25_000_000,
        10_000 => 50_000_000,
        20_000 => 100_000_000,
        35_000 => 175_000_000,
        50_000 => 250_000_000,
        65_000 => 325_000_000,
        80_000 => 400_000_000,
        90_000 => 450_000_000,
        95_000 => 475_000_000,
        100_000 => 500_000_000,
    ],
];
