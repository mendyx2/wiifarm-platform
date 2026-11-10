<?php

/*
 * The ledger's accounts (specification sections 1, 6 and 7).
 *
 * Amounts are whole numbers in the currency's smallest unit: kobo for naira
 * (₦1 = 100), and hundredths of a point for PV (1 PV = 100).
 */
return [
    /*
     * Each member's wallets (section 6). "spend" says what the wallet may pay
     * for; the rules themselves arrive with each feature.
     */
    'member_wallets' => [
        'main' => 'Main balance',
        'visa' => 'VISA wallet',
        'product' => 'Product wallet',
        'autoship' => 'Autoship',
        'savings' => 'Savings wallet',
        'bonus.direct' => 'Direct referral bonus',
        'bonus.indirect' => 'Indirect referral bonus',
        'bonus.upgrade' => 'Upgrade bonus',
        'bonus.matching' => 'Binary matching bonus',
        'bonus.leadership' => 'Leadership bonus',
        'bonus.unilevel' => 'Unilevel bonus',
        'bonus.keyin' => 'Key-in bonus',
        'bonus.awards' => 'Awards',
        'bonus.repurchase_award' => 'Repurchase award',
        'bonus.acb' => 'Achievers / Upline Celebrated Bonus',
        'bonus.affiliate' => 'Affiliate bonus',
        'bonus.stockist_rebate' => 'Stockist product rebate',
        'bonus.stockist_sponsor' => 'Stockist sponsorship rebate',
    ],

    /*
     * The platform's own accounts. These may go below zero: "external" is the
     * other side of money coming in and going out, "adjustments" the other side
     * of admin corrections, and a pool budget line below zero is an overrun,
     * which must be reported, never hidden (section 7).
     */
    'system_accounts' => [
        'external' => 'Money in and out of the platform',
        'adjustments' => 'Admin corrections',

        // Section 7: how each naira of revenue is allocated.
        'company' => 'Company return (30%)',
        'executive' => 'Executive and frontline leadership (10%)',
        'operations.platform' => 'Operations: platform maintenance (0.50%)',
        'operations.marketing' => 'Operations: marketing and business development (1.00%)',
        'operations.expansion' => 'Operations: business expansion and retained earnings (3.00%)',
        'operations.contingency' => 'Operations: miscellaneous and contingency (0.50%)',

        // Consumer Rewards Pool (22%)
        'crp.keyin' => 'Consumer Rewards Pool: key-in bonus (1.00%)',
        'crp.direct' => 'Consumer Rewards Pool: direct referral bonus (6.00%)',
        'crp.indirect' => 'Consumer Rewards Pool: indirect referral bonus (5.00%)',
        'crp.matching' => 'Consumer Rewards Pool: binary matching bonus (8.00%)',
        'crp.upgrade' => 'Consumer Rewards Pool: upgrade bonus (2.00%)',

        // Commerce Pool (24.75%)
        'cp.unilevel' => 'Commerce Pool: unilevel bonus (9.00%)',
        'cp.leadership' => 'Commerce Pool: leadership bonus (4.00%)',
        'cp.repurchase' => 'Commerce Pool: repurchase bonus (5.00%)',
        'cp.stockist_sponsor' => 'Commerce Pool: stockist sponsorship rebate (3.00%)',
        'cp.stockist_product' => 'Commerce Pool: stockist product rebate (3.75%)',

        // Strategic Reserve (8.25%)
        'sr.contingency' => 'Strategic Reserve: contingency reserve (1.00%)',
        'sr.awards' => 'Strategic Reserve: rank achievement awards (4.50%)',
        'sr.acb' => 'Strategic Reserve: Achievers / Upline Celebrated Bonus (1.50%)',
        'sr.global_share' => 'Strategic Reserve: Global Share (1.25%)',
    ],
];
