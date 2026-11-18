<?php

/*
 * Online payments (specification section 6). Amounts in kobo.
 */
return [
    // A deposit to the main balance through Paystack.
    'deposit_min' => 100_000,          // ₦1,000
    'deposit_max' => 500_000_000,      // ₦5,000,000

    // Payments still waiting are checked with Paystack, in case its message
    // never arrived, for this long; then the wait is given up.
    'check_for_hours' => 48,
];
