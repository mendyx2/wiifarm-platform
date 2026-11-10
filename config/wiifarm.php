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
];
