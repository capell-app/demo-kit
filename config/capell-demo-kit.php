<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Demo Generation
    |--------------------------------------------------------------------------
    |
    | Leave seed as null for a fresh random demo on each run. Set an integer
    | seed when screenshots, tests, or bug reports need a repeatable demo.
    |
    */
    'seed' => null,

    'counts' => [
        'sites' => 3,
        'languages_per_site' => [1, 4],
        'pages_per_site' => [12, 30],
        'page_depth' => [1, 4],
        'media_per_page' => [0, 2],
    ],

    'health' => [
        'minimum_widget_count' => 8,
        'minimum_media_count' => 8,
        'homepage_opening_widget_keys' => [
            'capell-home-hero-command-center',
        ],
        'showcase_widget_order' => [
            'capell-home-hero-command-center',
            'capell-home-proof-strip',
            'capell-home-demo-showcase',
            'capell-home-demo-widgets-carousel',
            'capell-extension-marketplace-showcase',
            'capell-home-technical-pipeline',
            'capell-home-route-split',
            'capell-home-final-cta',
        ],
        'widget_asset_minimums' => [],
        'placeholder_labels' => [
            'AP Card Grid',
            'AP Feature List',
            'Editorial Workflow',
            'Our Work',
            'Meet Our Team',
            'Client Logos',
        ],
    ],

    'archive' => [
        'url' => 'https://capell.app/demo.zip',
        'checksum' => 'cf39f86a46f45bc9246352472dbbc39f70d79ee52de06e3c04f51b58fb436957',
        'max_bytes' => 52428800,
    ],
];
