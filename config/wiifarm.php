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
    'mobile_code_attempts' => 5,
];
