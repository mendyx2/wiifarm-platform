<?php

/*
 * The cooperative's pool ledgers and the board's Allocation Blueprint V3
 * (specification section 7; docs/board/allocation-blueprint-v3.xlsx).
 *
 * Each pool is its own ledger: its own accounts, one per line, and its own
 * contingency fund. Money never moves from one pool to pay another pool's
 * bonuses. Amounts are whole kobo; percentages are text with two decimals.
 *
 * The "schedule" below is version 1 of the allocation schedule. It is copied
 * into the database when the app is installed, and every split records which
 * version it used, so a later change applies from then on, never backwards.
 */
return [
    'pools' => [
        'A' => [
            'name' => 'Registration (Pool A)',
            'lines' => [
                'direct' => 'Direct referral bonus',
                'indirect' => 'Indirect referral bonus',
                'keyin' => 'Key-in bonus',
                'matching' => 'Binary matching bonus',
                'leadership' => 'Leadership bonus',
                'stockist_rebate' => 'Stockist rebate',
            ],
        ],
        'B' => [
            'name' => 'Strategic Reserve (Pool B)',
            'lines' => [
                'recognition' => 'Recognition (rank) awards',
                'ucb' => 'Upline Celebrated Bonus',
            ],
        ],
        'C' => [
            'name' => 'Repurchase (Pool C)',
            'lines' => [
                'unilevel' => 'Unilevel bonus',
                'repurchase_award' => 'Repurchase awards',
                'sub_leaders' => 'Sub-leaders commission',
                'stockist_rebate' => 'Stockist rebate',
            ],
        ],
        'D' => [
            'name' => 'Stockist activation (Pool D)',
            'lines' => [
                'sponsor_bonus' => 'Stockist sponsor bonus',
            ],
        ],
        'E' => [
            'name' => 'Upgrade (Pool E)',
            'lines' => [
                'upgrade' => 'Upgrade bonuses',
            ],
        ],
        'L' => [
            'name' => 'Executive Leaders',
            'lines' => [
                'held' => 'Executive Leaders Bonus (held until its rules are set)',
            ],
        ],
        // Not a cooperative pool: what a public sale holds back until pickup.
        'P' => [
            'name' => 'Public sales (held until pickup)',
            'lines' => [
                'affiliate' => 'Affiliate commission, paid on pickup',
                'stockist' => 'Stockist rebate, paid on pickup',
            ],
        ],
    ],

    // Every pool also has this account.
    'contingency' => 'Contingency fund',

    'schedule' => [
        // Every member purchase: manufacturing company and cooperative.
        'member_split' => ['manufacturer' => '50.00', 'cooperative' => '50.00'],

        // The cooperative's share of a first purchase. "B" and "L" are the
        // Strategic Reserve and Executive Leaders ledgers; "net" is shared by
        // the "net" lines below. What rounding leaves goes to the contingency fund.
        'registration' => [
            'top' => ['B' => '1.00', 'A.contingency' => '2.30', 'L' => '10.00', 'net' => '86.70'],
            'net' => [
                'A.direct' => '34.62', 'A.keyin' => '2.77', 'A.indirect' => '18.00',
                'A.matching' => '16.61', 'A.leadership' => '9.50', 'A.stockist_rebate' => '18.50',
            ],
        ],
        'upgrade' => [
            'top' => ['B' => '1.00', 'L' => '10.00', 'net' => '89.00'],
            'net' => ['E.upgrade' => '52.50', 'E.contingency' => '47.50'],
        ],
        'repurchase' => [
            'top' => ['C.unilevel' => '49.00', 'C.repurchase_award' => '10.00', 'C.sub_leaders' => '10.00', 'C.stockist_rebate' => '16.00', 'C.contingency' => '15.00'],
        ],
        'strategic_reserve' => ['B.recognition' => '70.00', 'B.ucb' => '30.00'],
        'executive_leaders' => ['L.held' => '100.00'],

        // A public sale stays outside the cooperative (section 8).
        'public' => [
            'member_unit_price' => 150_000,
            'affiliate_share_of_margin' => '20.00',
        ],

        // Paid per unit handed over (section 9). The largest is held back from a public sale.
        'stockist_rebate' => ['mega' => 12_000, 'sub' => 9_000],

        // Of a store's activation deposit, to Pool D (section 9).
        'activation' => ['sub' => '8.00', 'mega' => '6.50'],
    ],
];
