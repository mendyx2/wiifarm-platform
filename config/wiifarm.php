<?php

return [
    /*
     * The company account: member #1. Anyone who signs up without a referral
     * is sponsored by it (specification section 2).
     */
    'company_username' => env('WIIFARM_COMPANY_USERNAME', 'wiifarm'),

    /*
     * Default dialling code and country for the sign-up form.
     */
    'default_dial_code' => '234',
    'default_country' => 'NG',

    /*
     * Every admin setting and its default (specification section 1). Admins
     * change them from the admin panel; the change is logged.
     */
    'settings' => [
        // Members must confirm their email / phone before using the dashboard.
        'verification.email_required' => false,
        'verification.mobile_required' => false,

        // Members must have their identity verified (KYC) before they can withdraw.
        'withdrawals.kyc_required' => true,

        // Until a member makes the monthly purchase below, 20% of each
        // matching bonus goes to their autoship wallet (sections 5.5 and 5.6;
        // open decision 7 asks whether this hold stays).
        'matching.autoship_hold' => true,

        // Member messages by email (section 12). Each message can also be
        // switched off on its own, on the admin Messages page.
        'messages.email' => true,

        // USDT (TRC20) deposits (section 6): off until an admin sets
        // Wiifarm's address and the naira paid for 1 USDT.
        'usdt.enabled' => false,
        'usdt.address' => '',
        'usdt.naira_per_usdt' => 0,

        // The monthly purchase that keeps a member active, in PV (Revised
        // Baseline Rules, 19 November 2026: 10.5 PV, ₦10,500).
        'activity.monthly_pv' => 10.5,

        // Who counts as a leader's "team" for the leadership bonus (section
        // 5.7; open decision 3): "tree" (everyone below them in the binary
        // tree) or "sponsor" (everyone down their sponsor chain), to this many
        // levels or generations; 0 means no limit.
        'leadership.team' => 'tree',
        'leadership.levels' => 0,

        // The fields of the KYC form (specification section 2). Types: text,
        // select (with options) and file (an image or PDF, stored privately).
        'kyc.fields' => [
            ['key' => 'nin', 'label' => 'National Identification Number (NIN)', 'type' => 'text', 'required' => true, 'pattern' => '^[0-9]{11}$', 'hint' => '11 digits'],
            ['key' => 'id_type', 'label' => 'ID document', 'type' => 'select', 'required' => true, 'options' => ['National ID card', 'International passport', "Driver's licence", "Voter's card"]],
            ['key' => 'id_front', 'label' => 'Photo of the ID document', 'type' => 'file', 'required' => true],
            ['key' => 'selfie', 'label' => 'A clear photo of your face', 'type' => 'file', 'required' => true],
        ],
    ],

    /*
     * Phone verification codes: how long a code lasts and how many wrong
     * tries are allowed before a new code is needed.
     */
    'mobile_code_minutes' => 10,

    /*
     * The product that fills every package's packs (specification section 4).
     */
    'registration_product_sku' => env('WIIFARM_REGISTRATION_PRODUCT_SKU', 'WF-PACK'),
    'mobile_code_attempts' => 5,

    /*
     * The time zone of the business day: the matching run closes each day at
     * midnight Lagos time (section 12).
     */
    'business_timezone' => 'Africa/Lagos',

    /*
     * The public shop (specification section 8): how long an affiliate's link
     * is remembered, and how long an unpaid public order waits before it is
     * cancelled. A paid order's pickup code never expires.
     */
    'affiliate_cookie_days' => 30,
    'public_order_days' => 3,
];
