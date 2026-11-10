<?php

/*
 * Inertia settings. Only the test settings differ from the package defaults:
 * our pages live in resources/js/pages (lower case) and are .tsx files, so
 * tests that check which page was shown can find them.
 */
return [
    'ssr' => [
        'enabled' => false,
        'url' => 'http://127.0.0.1:13714',
    ],

    'testing' => [
        'ensure_pages_exist' => true,
        'page_paths' => [resource_path('js/pages')],
        'page_extensions' => ['tsx'],
    ],

    'history' => [
        'encrypt' => false,
    ],
];
