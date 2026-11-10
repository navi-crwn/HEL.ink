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

    // Requests per hour for an API token that has no limit of its own.
    'api_rate_limit' => 100,

    // Hosts the chained logout may send the browser on to (`next`), besides the hub,
    // this app and the app URLs in config/hop.php. Exact hostnames only: no wildcards.
    // Self-hosted family on your own domain: set HOP_TRUSTED_HOSTS to a comma-separated list.
    'trusted_hosts' => array_values(array_filter(array_map('trim', explode(',', (string) env(
        'HOP_TRUSTED_HOSTS',
        'hel.ink,www.hel.ink,hub.hel.ink,link.hel.ink,bio.hel.ink,note.hel.ink,pixel.hel.ink,file.hel.ink,pay.hel.ink'
    ))))),

    // Silent account re-check: after `max_age` seconds without the hub vouching for this session, a page load (GET,
    // not XHR, not a prefetch) makes one `prompt=none` round trip to the hub. Same account: carry on unseen. Another
    // account: switch to it. `login_required`: sign out here. Nothing in the database: the timestamps are in the session.
    'recheck' => [
        'enabled' => filter_var(env('HOP_RECHECK', true), FILTER_VALIDATE_BOOLEAN),
        // Seconds a session counts as fresh; 0 turns the check off.
        'max_age' => (int) env('HOP_RECHECK_MAX_AGE', 300),
        // At most one attempt per this many seconds in a session (no loops).
        'cooldown' => 30,
        // Seconds to wait after the hub answered with an error (e.g. temporarily_unavailable).
        'retry_after' => 300,
        // Do not redirect to a hub that does not accept a TCP connection within probe_timeout seconds.
        'probe' => true,
        'probe_timeout' => 1.5,
    ],

    // Account events from the hub (POST /hop/events: session.revoked, account.status, account.deleted, profile.updated).
    'events' => [
        // The app slug the hub signs its messages with (X-HOP-App).
        'hub_app' => 'helink',
        // Seconds of clock difference tolerated on a signed message; each nonce works once within twice this.
        'max_age' => 120,
        // How long an event id is remembered so a retry is not handled twice (needs a cache that survives requests).
        'seen_ttl' => 172800,
    ],

    // Paths this package registers (set to null to register your own).
    'routes' => [
        'start' => '/auth/sso',
        'callback' => '/auth/sso/callback',
        'logout' => '/auth/sso/logout',
        // Off by default. Set it ('/hop/events') to let the package answer the hub's account events, or leave it
        // null and send the request to Hop\Auth\Http\Controllers\EventController from your own route.
        'events' => null,
    ],
];
