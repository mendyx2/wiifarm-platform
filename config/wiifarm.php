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

        // Until a member buys 10 PV of products in the month, 20% of each
        // matching bonus goes to their autoship wallet (sections 5.5 and 5.6;
        // open decision 7 asks whether this hold stays).
        'matching.autoship_hold' => true,

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
];
