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
    'upgrade' => ['25.00', '2.00', '1.00', '1.00', '1.00', '1.00', '1.00', '1.00', '1.00', '1.00'],
];
