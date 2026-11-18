<?php

/*
 * Bonus rates (specification section 5), as shares of PV value: PV × ₦600.
 * Generation 1 of the indirect bonus is the sponsor's sponsor; generation 1 of
 * the upgrade bonus is the sponsor.
 */
return [
    'direct' => '25.00',
    'indirect' => ['3.00', '2.00', '1.00', '1.00', '1.00', '1.00', '1.00', '1.00', '1.00', '1.00'],
    'keyin' => '2.00',
    // Matching (5.5 and 5.6): the share held in the autoship wallet until the
    // member buys this much PV of products in the month (hundredths of a PV).
    'matching' => ['autoship_share' => '20.00', 'autoship_month_pv' => 1_000],
    // Unilevel (5.10): shares of the PV value of each downline member's
    // repurchase PV that month, generation 1 to 10 up the referral chain.
    'unilevel' => ['10.00', '7.00', '5.00', '4.00', '4.00', '3.00', '3.00', '3.00', '2.00', '2.00'],

    // Leadership (5.7): a leader has this much repurchase PV (hundredths:
    // 480,000 PV), and earns this share of what their team earns from matching.
    'leadership' => ['qualifying_pv' => 48_000_000, 'share' => '5.00'],
    'upgrade' => ['25.00', '2.00', '1.00', '1.00', '1.00', '1.00', '1.00', '1.00', '1.00', '1.00'],
];
