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
        'autoship' => 'Matching held until autoship',
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
        'bonus.acb' => 'Upline Celebrated Bonus',
        'bonus.stockist_sponsor' => 'Stockist sponsor bonus',
    ],

    /*
     * Each member's PV, per leg of the binary tree (section 3), in hundredths
     * of a PV. Lifetime PV only grows (awards); pairing PV is used up by
     * matches, and what is left carries forward.
     */
    'member_pv_accounts' => [
        'pv.L.lifetime' => 'Left leg: lifetime PV',
        'pv.L.pairing' => 'Left leg: pairing PV',
        'pv.R.lifetime' => 'Right leg: lifetime PV',
        'pv.R.pairing' => 'Right leg: pairing PV',
        // The member's own repurchases, for unilevel, leadership and monthly activity.
        'pv.repurchase' => 'Repurchase PV (own purchases)',
        // Store PV from the referral chain (section 9): awards only, 10 generations.
        'pv.team_store' => 'Store PV from your referral chain',
    ],

    /*
     * The platform's PV accounts: where PV comes from when a member gains it,
     * and where pairing PV goes when it is used by a match.
     */
    'system_pv_accounts' => [
        'pv.issued' => 'PV issued (registrations, upgrades, purchases, stores)',
        'pv.matched' => 'Pairing PV used by matches',
    ],

    /*
     * The platform's own accounts. "external" is the other side of money
     * coming in and going out; "adjustments" the other side of admin
     * corrections. Both may go below zero. The cooperative's pools and the
     * manufacturing partners have their own accounts (config/pools.php).
     */
    'system_accounts' => [
        'external' => 'Money in and out of the platform',
        'adjustments' => 'Admin corrections',
        'withdrawals.pending' => 'Withdrawals waiting for an admin',
        'epins.outstanding' => 'E-pins issued and not yet used',
    ],

    /*
     * The cooperative's own accounts, outside its bonus pools: what it earns
     * from charges (section 6).
     */
    'cooperative_accounts' => [
        'withdrawal_charges' => 'Withdrawal charges',
        'transfer_charges' => 'Balance transfer charges',
        // A recognition award taken as a gift: its cash value, set aside to buy the gift.
        'award_gifts' => 'Award gifts to buy',
    ],
];
