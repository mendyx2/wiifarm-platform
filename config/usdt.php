<?php

/*
 * USDT (TRC20) deposits to Wiifarm's one address (specification section 6).
 * The address, the naira rate and the on/off switch are admin settings
 * (Deposits > Deposit methods). The TronGrid API key is a secret and goes
 * only in the server's settings (TRONGRID_API_KEY).
 */
return [
    // "mainnet" for real money; "nile" is Tron's test network.
    'network' => env('USDT_TRON_NETWORK', 'mainnet'),
    'networks' => [
        'mainnet' => ['api' => 'https://api.trongrid.io', 'contract' => 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t', 'explorer' => 'https://tronscan.org/#/transaction/'],
        'nile' => ['api' => 'https://nile.trongrid.io', 'contract' => 'TXYZopYRdj2D9XRtbG411XZZ3kM5VkAeBf', 'explorer' => 'https://nile.tronscan.org/#/transaction/'],
    ],
    'api_key' => env('TRONGRID_API_KEY'),

    // Minutes a member has to send the exact amount once it is shown.
    'window_minutes' => 60,
    // A payment may be stamped this many seconds before the deposit was started (clocks differ).
    'skew_seconds' => 300,
    // Days a member can still claim a payment with its transaction ID; after that, an admin decides.
    'claim_days' => 7,
];
