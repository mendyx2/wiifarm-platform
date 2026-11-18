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
    'upgrade' => ['25.00', '2.00', '1.00', '1.00', '1.00', '1.00', '1.00', '1.00', '1.00', '1.00'],
];
