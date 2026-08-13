<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Pages
    |--------------------------------------------------------------------------
    |
    | Inertia v3 changed the default page directory to `resources/js/pages`,
    | but this app has always used `resources/js/Pages` (the v2 default, and
    | the casing `resolvePage.js` globs for). Point the server-side view
    | finder back at it so `assertInertia()->component()` can locate pages.
    |
    | The whole block is repeated because the package config is merged
    | shallowly — overriding `pages` replaces it wholesale.
    |
    */

    'pages' => [

        'ensure_pages_exist' => false,

        'paths' => [

            resource_path('js/Pages'),

        ],

        'extensions' => [

            'js',
            'jsx',
            'svelte',
            'ts',
            'tsx',
            'vue',

        ],

    ],

];
