<?php

return [

    'name' => env('APP_NAME', 'Dashboard Invest'),

    'short_name' => env('PWA_SHORT_NAME', 'Invest'),

    'description' => env('PWA_DESCRIPTION', 'Dashboard de suivi des investissements'),

    'theme_color' => env('PWA_THEME_COLOR', '#000000'),

    'background_color' => env('PWA_BACKGROUND_COLOR', '#ffffff'),

    'display' => 'standalone',

    'start_url' => '/admin',

    'scope' => '/',

    'icons' => [
        [
            'src' => '/icons/icon-192x192.png',
            'sizes' => '192x192',
            'type' => 'image/png',
        ],
        [
            'src' => '/icons/icon-512x512.png',
            'sizes' => '512x512',
            'type' => 'image/png',
        ],
    ],

];
