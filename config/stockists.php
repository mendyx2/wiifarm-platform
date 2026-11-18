<?php

/*
 * Stockist stores (specification section 9). Naira amounts are in kobo and
 * PV in hundredths. The shares of a deposit that go to Pool D, and the
 * rebates per unit, are in the allocation schedule (config/pools.php).
 */
return [
    'kinds' => [
        'sub' => [
            'name' => 'Sub store',
            'deposit' => 300_000_000,       // ₦3,000,000
            'store_pv' => 300_000,          // 3,000 PV, awards only
            // Stockist sponsor bonus, generations 1 to 10 (₦240,000 in all)
            'sponsor_bonus' => [5_000_000, 3_000_000, 2_000_000, 2_000_000, 2_000_000, 2_000_000, 2_000_000, 2_000_000, 2_000_000, 2_000_000],
        ],
        'mega' => [
            'name' => 'Mega store',
            'deposit' => 1_000_000_000,     // ₦10,000,000
            'store_pv' => 1_000_000,        // 10,000 PV, awards only
            // ₦650,000 in all
            'sponsor_bonus' => [15_000_000, 10_000_000, 5_000_000, 5_000_000, 5_000_000, 5_000_000, 5_000_000, 5_000_000, 5_000_000, 5_000_000],
        ],
    ],

    // How far store PV reaches up the referral chain (board, November 2026).
    'store_pv_generations' => 10,
];
