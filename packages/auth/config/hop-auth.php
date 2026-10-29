<?php

/*
| hop/auth: the consumer side of HOP ID for LinkHop, BioHop and any other app.
| The hub (hub.hel.ink) signs people in and hands this app a ticket; this
| package verifies it and signs the same user in here (shared users table).
*/
return [
    // This app's slug in the hub's config/hop.php (linkhop, biohop, …).
    'app' => env('HOP_APP', 'linkhop'),

    // The hub that issues tickets.
    'hub_url' => rtrim((string) env('HOP_HUB_URL', 'https://hub.hel.ink'), '/'),

    // The shared HMAC secret (the same value as HOP_SSO_SECRET at the hub, or this app's own).
    'secret' => env('HOP_SSO_SECRET'),

    // Previous secret accepted during transition/rotation (optional).
    'secret_prev' => env('HOP_SSO_SECRET_PREV'),

    // Seconds of clock skew tolerated when checking a ticket's exp/iat.
    'leeway' => 30,

    // The user model to sign in (must use the shared users table).
    'user_model' => env('HOP_USER_MODEL', 'App\\Models\\User'),

    // Where to land after a successful sign-in when the app did not say.
    'home' => '/dashboard',

    // Paths this package registers (set to null to register your own).
    'routes' => [
        'start' => '/auth/sso',
        'callback' => '/auth/sso/callback',
        'logout' => '/auth/sso/logout',
    ],
];
