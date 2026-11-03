<?php

/*
|--------------------------------------------------------------------------
| HOP: the family of apps behind hel.ink
|--------------------------------------------------------------------------
| This app is the hub: the public home page on `public_host` (hel.ink) and
| the account portal ("HOP ID") on `hub_host` (hub.hel.ink). The other apps
| (LinkHop, BioHop, PixelHop) sign people in through the hub (SSO tickets)
| and share one database with it (see README, "Database bersama").
|
| The same file, with `current` set to the app's own slug, is what hop/ui
| reads in every app to draw the cross-app navigation.
*/
return [
    'brand' => env('HOP_BRAND', 'HOP'),
    'tagline' => env('HOP_TAGLINE', 'Hop Easy Link'),

    // Which app this is (a key of `apps`, or "hub").
    'current' => 'hub',

    // Hosts served by this app (Route::domain). Defaults suit `php artisan serve` and tests.
    'public_host' => env('HUB_PUBLIC_HOST', 'localhost'),
    'hub_host' => env('HUB_HOST', 'localhost'),
    'public_url' => rtrim(env('HUB_PUBLIC_URL', env('PUBLIC_URL', 'http://localhost')), '/'),
    'hub_url' => rtrim(env('APP_URL', 'http://localhost'), '/'),

    // The shared HMAC secret for SSO tickets. Per-app overrides: HOP_SSO_SECRET_LINKHOP, …
    'sso_secret' => env('HOP_SSO_SECRET'),

    // Imperium, the admin control panel. Admins who sign in at the hub land there
    // (the hub dashboard stays one click away). citadel.hel.ink works from anywhere
    // through Cloudflare Access; imperium.hel.ink only over Tailscale.
    'control_url' => rtrim((string) env('HOP_CONTROL_URL', ''), '/'),

    // Apps from the HOP ID registry table (`apps`), which has no column for these.
    'registry_taglines' => [
        'notehop' => 'Encrypted notes you share with a link.',
    ],
    'registry_repos' => [
        'notehop' => 'https://github.com/navi-crwn/NoteHop',
    ],
    // Apps that must have a row in `apps` with the `events` capability, or the hub's account
    // events (suspend, delete, sign-out) never reach them. `php artisan db:seed
    // --class='Database\Seeders\HopAppsSeeder'` adds a missing row; it never changes an existing one.
    // The secret of a new row comes from the environment (HOP_SSO_SECRET_PIXELHOP, HOP_SSO_SECRET_NOTEHOP).
    'event_apps' => [
        'pixelhop' => [
            'menu_label' => 'Images',
            'pages' => ['default' => '/dashboard', 'upload' => '/'],
            'continue_page' => 'upload',
            'sort_order' => 10,
        ],
        'notehop' => [
            'callback_path' => env('NOTEHOP_SSO_CALLBACK', '/auth/sso/callback'),
            'start_path' => env('NOTEHOP_SSO_START', '/auth/sso'),
            'secret' => env('HOP_SSO_SECRET_NOTEHOP'),
            'menu_label' => 'Notes',
            'pages' => ['default' => '/dashboard', 'new' => '/'],
            'continue_page' => 'default',
            'sort_order' => 20,
        ],
    ],
    'registry_health' => [],   // default /healthz
    'registry_logout' => [
        'notehop' => '/auth/sso/logout',
    ],   // slug => path that signs the visitor out, e.g. '/auth/sso/logout'

    // Seconds a sign-in ticket for an app stays valid.
    'ticket_ttl' => 120,

    // The apps, in launcher order. `url` empty: the app is not configured and is
    // skipped everywhere (launcher, SSO, watchdog).
    'apps' => [
        'linkhop' => [
            'name' => 'LinkHop',
            'tagline' => 'Short links, QR codes and click analytics.',
            'url' => env('LINKHOP_URL', 'https://link.hel.ink'),
            'public_prefix' => '/l/',
            'callback' => env('LINKHOP_SSO_CALLBACK', '/auth/sso/callback'),
            'logout' => env('LINKHOP_LOGOUT_PATH', '/auth/sso/logout'),
            'export' => '/settings#backup',
            'health' => '/up',
            'check' => env('LINKHOP_CHECK_URL'),
            'secret' => env('HOP_SSO_SECRET_LINKHOP'),
            'icon' => 'link',
            'color' => 'indigo',
            'repo' => 'https://github.com/navi-crwn/LinkHop',
            'enabled' => (bool) env('LINKHOP_ENABLED', true),
        ],
        'biohop' => [
            'name' => 'BioHop',
            'tagline' => 'One link-in-bio page for everything you do.',
            'url' => env('BIOHOP_URL', 'https://bio.hel.ink'),
            'public_prefix' => '/b/',
            'callback' => env('BIOHOP_SSO_CALLBACK', '/auth/sso/callback'),
            'logout' => env('BIOHOP_LOGOUT_PATH', '/auth/sso/logout'),
            'export' => '/settings#export',
            'health' => '/up',
            'check' => env('BIOHOP_CHECK_URL'),
            'secret' => env('HOP_SSO_SECRET_BIOHOP'),
            'icon' => 'bio',
            'color' => 'fuchsia',
            'repo' => 'https://github.com/navi-crwn/BioHop',
            'enabled' => (bool) env('BIOHOP_ENABLED', true),
        ],
        'pixelhop' => [
            'name' => 'PixelHop',
            'tagline' => 'Image hosting with albums and direct links.',
            'url' => env('PIXELHOP_URL', 'https://pixel.hel.ink'),
            'public_prefix' => null,
            'callback' => env('PIXELHOP_SSO_CALLBACK', '/auth/helink/callback'),
            'start' => env('PIXELHOP_SSO_START', '/auth/helink'),
            'logout' => env('PIXELHOP_LOGOUT_PATH', '/auth/helink/logout'),
            'export' => null,
            // PixelHop is not Laravel: its health endpoint is /healthz, not /up.
            'health' => env('PIXELHOP_HEALTH_PATH', '/healthz'),
            'check' => env('PIXELHOP_CHECK_URL'),
            'secret' => env('HOP_SSO_SECRET_PIXELHOP', env('PIXELHOP_SSO_SECRET')),
            'icon' => 'image',
            'color' => 'sky',
            'repo' => null,
            'enabled' => (bool) env('PIXELHOP_ENABLED', true),
        ],
        'notehop' => [
            'name' => 'NoteHop',
            'tagline' => 'Encrypted notes you share with a link.',
            'url' => env('NOTEHOP_URL', 'https://note.hel.ink'),
            // Signs in with its own secret and callback from the HOP ID registry (`apps`).
            'registry' => true,
            'public_prefix' => null,
            'logout' => env('NOTEHOP_LOGOUT_PATH', '/auth/sso/logout'),
            'export' => null,
            'health' => env('NOTEHOP_HEALTH_PATH', '/healthz'),
            'check' => env('NOTEHOP_CHECK_URL'),
            'icon' => 'note',
            'color' => 'emerald',
            'repo' => 'https://github.com/navi-crwn/NoteHop',
            'enabled' => (bool) env('NOTEHOP_ENABLED', true),
        ],
    ],

    // What each app tells the hub about one account, for the numbers on the dashboard cards.
    // `auth` is how the hub signs the call: "hub" (Laravel apps: /internal/accounts/{id}/summary,
    // shared SSO secret) or "hop" (Go apps: the HOP App Protocol, GET /hop/users/{id}).
    // Counts only, never content. `fields` maps the app's answer to a label (English key, translated on render).
    'summaries' => [
        'linkhop' => ['auth' => 'hub', 'path' => '/internal/accounts/{id}/summary', 'fields' => ['links' => 'Links', 'clicks_7d' => 'Clicks, 7 days']],
        'biohop' => ['auth' => 'hub', 'path' => '/internal/accounts/{id}/summary', 'fields' => ['pages' => 'Pages', 'published' => 'Published', 'clicks_7d' => 'Clicks, 7 days']],
        'pixelhop' => ['auth' => 'hop', 'path' => '/hop/users/{id}', 'fields' => ['images' => 'Files', 'albums' => 'Albums', 'storage_bytes' => 'Storage:bytes']],
        'notehop' => ['auth' => 'hop', 'path' => '/hop/users/{id}', 'fields' => ['notes' => 'Notes', 'notes_public' => 'Basic notes']],
    ],

    // Watchdog (hop:watch): alert after this many failures in a row.
    'watch' => [
        'failures_before_alert' => 3,
        'timeout' => 8,
        // The 30-day uptime strip on the home page needs a month of probes.
        'keep_days' => 31,
        'alert_email' => env('WATCH_ALERT_EMAIL', env('ADMIN_EMAIL')),
        'telegram_token' => env('TELEGRAM_BOT_TOKEN'),
        'telegram_chat_id' => env('TELEGRAM_CHAT_ID'),
    ],

    // Repo statistics for the home page, fetched on the server by `hop:github-stats`
    // (the browser never calls GitHub). The token is optional and read-only; without it
    // the 60 requests an hour are enough, because unchanged answers (304) cost nothing.
    'github' => [
        'api' => 'https://api.github.com',
        'org_url' => 'https://github.com/navi-crwn',
        'repos' => [
            'navi-crwn/HEL.ink',
            'navi-crwn/LinkHop',
            'navi-crwn/BioHop',
            'navi-crwn/PixelHop',
            'navi-crwn/NoteHop',
        ],
        'token' => env('GITHUB_TOKEN'),
        'timeout' => 10,
    ],

    // Home page uptime strip (from the watchdog's service_checks): a day counts as
    // "down" when less than this share of its probes answered.
    'uptime' => [
        'days' => 30,
        'down_below' => 99.0,
        'cache_seconds' => 300,
    ],

    // Days a released username stays unavailable to other people.
    'username_hold_days' => 90,

    // Usernames nobody can take: site routes of every app, staff-looking names,
    // and the brands. Lowercase. Prefixes end with "-".
    'reserved_usernames' => [
        'hop', 'hopid', 'hop-id', 'imperium', 'citadel', 'linkhop', 'pixelhop', 'notehop', 'remotehop', 'biohop',
        'helink', 'hel', 'hel-ink', 'hub', 'admin', 'administrator', 'root', 'system', 'moderator', 'mod',
        'staff', 'team', 'official', 'support', 'help', 'abuse', 'report', 'dmca', 'security', 'verify',
        'verified', 'legal', 'billing', 'owner', 'sysadmin', 'webmaster', 'postmaster', 'hostmaster',
        'noreply', 'no-reply', 'mailer-daemon',
        'api', 'app', 'apps', 'about', 'settings', 'login', 'logout', 'register', 'signup', 'signin',
        'dashboard', 'tools', 'docs', 'static', 'assets', 'public', 'storage', 'avatar', 'avatars',
        'profile', 'account', 'accounts', 'user', 'users', 'u', 'b', 'l', 'n', 'i', 'new', 'home', 'explore',
        'search', 'upload', 'uploads', 'album', 'albums', 'image', 'images', 'note', 'notes', 'link',
        'links', 'bio', 'folders', 'tags', 'analytics', 'password', 'reset', 'confirm', 'email', 'sso',
        'auth', 'oauth', 'callback', 'health', 'healthz', 'metrics', 'status', 'contact', 'terms', 'privacy',
        'faq', 'blog', 'news', 'shop', 'store', 'pricing', 'plans', 'download', 'downloads', 'share',
        'embed', 'oembed', 'sitemap', 'robots', 'manifest', 'sw', 'offline', 'raw', 'mock', 'nearby',
        'vault', 'diff', 'in', 'inbox', 'live', 'render', 'i18n', 'legacy', 'announcements', 'launcher',
        'null', 'undefined', 'none', 'nobody', 'anonymous', 'guest', 'me', 'you', 'everyone', 'test', 'demo',
    ],
    'reserved_username_prefixes' => ['hop-', 'hop_', 'admin-', 'admin_', 'official-', 'official_', 'staff-', 'staff_',
        'support-', 'support_', 'team-', 'team_', 'security-', 'security_', 'verify-', 'verified-', 'linkhop-',
        'pixelhop-', 'notehop-', 'remotehop-', 'biohop-', 'helink-', 'imperium-'],
];
