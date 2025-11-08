<?php

return [
    'invision' => [
        'client_id' => env('INVISION_CLIENT_ID'),
        'client_secret' => env('INVISION_CLIENT_SECRET'),
        'redirect' => env('INVISION_REDIRECT_URI'),
        'base_url' => env('INVISION_BASE_URL'),
        'scopes' => ['profile', 'email'],
    ],
];
