# hop/auth

**English** · [简体中文](README.zh-CN.md) · [Deutsch](README.de.md) · [Bahasa Indonesia](README.id.md) · [日本語](README.ja.md) · [Português (Brasil)](README.pt-BR.md) · [Español](README.es.md) · [Français](README.fr.md) · [Русский](README.ru.md)

The Laravel client for HOP ID, the account service of the HOP family ([hel.ink](https://hel.ink)). It lets an app in the family sign people in through the hub at `hub.hel.ink`, check the sign-in ticket the hub sends back, accept scoped API tokens, and sign people out again.

The hub itself uses only the `ApiToken` model and the middleware aliases. The SSO routes are for the apps that consume HOP ID, such as LinkHop and BioHop.

## What it provides

- Three routes for the sign-in round trip: start, callback and logout.
- `Hop\Auth\Ticket`, which verifies a ticket signed with HMAC-SHA256. It checks the signature, the audience (this app), the expiry and the `state` the app sent. A previous secret is accepted during a rotation.
- The `hop.auth` middleware: like Laravel's `auth`, but a guest is sent through the hub instead of to a local login page.
- The `hop.token` middleware: bearer API tokens from the shared `api_tokens` table, with scopes, expiry and an hourly rate limit per token.
- A chained logout that only follows links to trusted hosts.
- A receiver for the hub's account events (`POST /hop/events`): signature check, once-only event ids, and hooks for the app. Signing out at the hub, an admin ending sessions, a suspension or a deletion reach the app this way, even if the app was down when it happened.

## Requirements

- PHP 8.3 and Laravel 12 (`illuminate/support` ^12.0).
- The family's shared `users` table. The package signs in the user whose id is the ticket's `sub`.
- For API tokens, the shared `api_tokens` table. The package does not ship a migration for it; tokens are made and revoked at the hub.
- A session and a cache store. The cache holds the single-use marker of each ticket and the rate limit counters.

## Installation

The package is not on Packagist. Add the repository to the app's `composer.json`:

```json
"repositories": [
    {
        "type": "vcs",
        "url": "https://github.com/navi-crwn/hop-auth.git"
    }
],
"require": {
    "hop/auth": "^0.1.2"
}
```

Then run:

```bash
composer update hop/auth
```

Laravel's package discovery registers `Hop\Auth\AuthServiceProvider`. The provider does not load the SSO routes when `config('hop.current')` is `hub`: the hub issues tickets and never consumes them.

## Configuration

Set these in the app's `.env`. Only `HOP_APP` and `HOP_SSO_SECRET` are usually needed:

```ini
HOP_APP=linkhop
HOP_HUB_URL=https://hub.hel.ink
HOP_SSO_SECRET=at-least-32-characters-shared-with-the-hub
# HOP_SSO_SECRET_PREV=only-while-rotating
# HOP_USER_MODEL=App\Models\User
# HOP_TRUSTED_HOSTS=only-for-a-self-hosted-family
```

| Variable | Default | Meaning |
| --- | --- | --- |
| `HOP_APP` | `linkhop` | This app's slug in the hub's `config/hop.php`. It is also the ticket audience. |
| `HOP_HUB_URL` | `https://hub.hel.ink` | The hub that issues tickets. |
| `HOP_SSO_SECRET` | none | The shared HMAC secret. Secrets shorter than 32 characters are ignored, and with no usable secret every ticket is refused as `not_configured`. |
| `HOP_SSO_SECRET_PREV` | none | The previous secret, accepted only while you rotate. |
| `HOP_USER_MODEL` | `App\Models\User` | The model signed in, and the owner of an API token. |
| `HOP_TRUSTED_HOSTS` | the production family hosts | Hosts the chained logout may redirect to. See below. |
| `HOP_RECHECK` | `true` | Silent account re-check on page loads (`false` turns it off). See below. |
| `HOP_RECHECK_MAX_AGE` | `300` | Seconds a session counts as fresh after the hub last vouched for it. `0` turns the re-check off. |

The remaining options have no environment variable. Publish the config file to change them:

```bash
php artisan vendor:publish --tag=hop-auth-config
```

| Key | Default | Meaning |
| --- | --- | --- |
| `leeway` | `30` | Seconds of clock skew tolerated when checking a ticket's `exp` and `iat`. |
| `home` | `/dashboard` | Where to land after sign-in when the app did not ask for a page. |
| `api_rate_limit` | `100` | Requests per hour for an API token that has no limit of its own. |
| `events.hub_app`, `events.max_age`, `events.seen_ttl` | `helink`, `120`, `172800` | Account events: the app slug the hub signs with, the seconds of clock difference tolerated (each nonce works once within twice this), and how long an event id is remembered. |
| `routes.events` | `null` | Path of the account-event receiver. Off by default; see below. |
| `routes.start`, `routes.callback`, `routes.logout` | `/auth/sso`, `/auth/sso/callback`, `/auth/sso/logout` | The route paths. Set `routes.start` to `null` to skip the package routes and register your own. |

## How a sign-in runs

1. A guest opens a page behind `hop.auth`. The middleware sends them to `/auth/sso?next=<page>`. A JSON request gets a `401` instead.
2. `start` stores a random `state` and the `next` page in the session, then redirects to `HOP_HUB_URL/sso/{HOP_APP}?state=…&return_to=<callback url>`. Only a same-site path is kept as `next`.
3. The hub signs the person in and redirects to `/auth/sso/callback?ticket=…`.
4. `callback` verifies the ticket and the state. It refuses a ticket that was already used (the `jti` is remembered for 5 minutes) and a suspended account (`status` of `suspended`).
5. It signs in the user whose id is `sub`, regenerates the session, keeps the ticket payload in the session as `hop_profile`, and redirects to `next` or to `home`.

A ticket is `base64url(json)` and `base64url(HMAC-SHA256)` joined by a dot. The JSON needs `sub`, `exp` and `state`. `aud`, `iat`, `jti` and `status` are optional. `Ticket::verify()` throws `Hop\Auth\TicketException` with one of these reasons: `not_configured`, `malformed`, `bad_signature`, `expired`, `wrong_audience`, `state_mismatch`.

### Secret rotation

Put the new secret in `HOP_SSO_SECRET` and the old one in `HOP_SSO_SECRET_PREV`. A ticket signed with either one is accepted. Remove the old value once the hub signs only with the new one.

## Protecting routes

```php
Route::middleware('hop.auth')->group(function () {
    Route::get('/dashboard', DashboardController::class);
});

Route::middleware('hop.token:link:create')->post('/api/links', StoreLinkController::class);
```

### API tokens

A client sends `Authorization: Bearer hlk_…`. The package stores only the SHA-256 hash of a token. `hop.token` answers with JSON of the form `{"success": false, "error": "…", "message": "…"}` and these statuses:

| Status | Cause |
| --- | --- |
| 401 | Missing or malformed header, unknown token, or expired token. |
| 403 | The account is suspended, or the token lacks the required scope. |
| 429 | The token's hourly limit is used up. |

On success the request carries the `api_user` and `api_token` attributes, and `$request->user()` returns the token's owner.

`hop.token` without a scope only checks that the token is valid. `hop.token:link:create` also needs the `link:create` scope. A token with no scopes (null or an empty list) may do everything. Otherwise `can()` accepts an exact match, a prefix wildcard such as `link:*`, or `*`. The scopes the family defines are in `ApiToken::SCOPES`: `link:read`, `link:create`, `link:write`, `bio:read`, `bio:write`, `pixel:read` and `pixel:write`.

### API token rate limit

Each token may make `rate_limit` requests per hour. A token whose `rate_limit` is empty or not above zero gets `hop-auth.api_rate_limit` (100 by default). The window starts at the token's first request and ends an hour later. The counter lives in the rate limiter's cache store and is incremented before it is compared with the limit, so parallel requests cannot all pass together.

A refused request gets a `429` with `retry_after` in the body and a matching `Retry-After` header, both in seconds until the window ends.

## Silent account re-check

A session in your app can outlive the account the person has at the hub: they sign in as someone else, or out, at the hub, and your app keeps the old account. `hop.auth` closes that gap with no migration:

- The session is **fresh** for `recheck.max_age` seconds (default 300) after the hub last vouched for it, by a sign-in or by a silent answer. The time is kept in the session.
- When it is not fresh, a page load (a `GET` that is not XHR or JSON and not a prefetch) makes one `prompt=none` round trip to the hub ([silent SSO](https://github.com/navi-crwn/HEL.ink/blob/main/docs/silent-sso.md)). Same account: the page loads as if nothing happened. Another account: the session switches to it. `login_required`: the person is signed out here and lands on the page as a guest. A protected page then starts the normal sign-in; a public page shows no sign-in at all.
- Safeguards: a POST or an XHR request is never redirected. Nothing is redirected while the hub does not accept a connection (a short probe, cached for 30 seconds). At most one attempt per `recheck.cooldown` seconds (30), so there is no loop. Any other hub error, such as `temporarily_unavailable`, keeps the session and waits `recheck.retry_after` seconds (300). The `state` must be the one the session started.
- Pages that need no sign-in but show the account (a landing page with a "Dashboard" button) get `hop.recheck` in their middleware, so they are checked too.
- The keys are in `hop-auth.recheck` (`enabled`, `max_age`, `cooldown`, `retry_after`, `probe`, `probe_timeout`). `HOP_RECHECK=false` turns the whole check off.

## Chained logout and trusted hosts

`/auth/sso/logout` signs the person out here, invalidates the session and regenerates the CSRF token. If `?next=` holds a trusted URL it then redirects there, which is how the hub signs a person out of every app. Anything else goes to `/`.

A `next` URL is trusted when all of this holds:

- Its host is exactly one of the trusted hosts. There is no wildcard matching: `evil.hel.ink` is not trusted because `hel.ink` is.
- It has no user info, no whitespace or control characters, and no backslash.
- It uses `https`. Plain `http` is accepted only for `localhost`, `127.0.0.1` and `[::1]`, and only when that host is in the trusted list.

The trusted hosts are `hop-auth.trusted_hosts` plus the hub (`HOP_HUB_URL`), this app (`APP_URL`), and the URLs in the shared `config/hop.php` (`hop.public_url`, `hop.hub_url` and each app's `url`).

The default list is the production family: `hel.ink`, `www.hel.ink`, `hub.hel.ink`, `link.hel.ink`, `bio.hel.ink`, `note.hel.ink`, `pixel.hel.ink`, `file.hel.ink` and `pay.hel.ink`. `HOP_TRUSTED_HOSTS` replaces that list. If you run the family on your own domain, set the hosts of your apps:

```ini
HOP_TRUSTED_HOSTS=example.org,hub.example.org,link.example.org,bio.example.org
```

## Account events from the hub

The hub tells every app that declares the `events` capability when something happens to an account, with `POST {app}/hop/events` and a body `{id, type, at, data}`. It signs the request with the app's secret (HOP App Protocol v1: headers `X-HOP-App: helink`, `X-HOP-Timestamp`, `X-HOP-Nonce`, `X-HOP-Signature`), and retries with backoff for about sixteen hours when the answer is not a 2xx. The types:

| Type | Sent when | Built in here |
| --- | --- | --- |
| `session.revoked` | the person signs out at the hub, or an admin ends their sessions | Ends every session of that account here and rotates its `remember_token`. |
| `account.deleted` | the account is deleted | Ends every session of that account here. Delete the app's own data with a handler of yours. |
| `account.status` | the status changes (`data.status`: `active`, `suspended`) | Nothing. LinkHop and BioHop already sign a suspended account out on its next request; add a handler if your app needs more. |
| `profile.updated` | the profile changes | Nothing. |

`data.sub` is the account id in the shared `users` table. Any other type gets a 200 `{handled: false}`, so the hub never retries what this app does not know.

Why the remember token matters: the sign-in of this package uses `remember: true`, so deleting a person's session rows is not enough. The remember cookie (400 days by default) would sign them straight back in. `SessionRevoker` deletes the sessions (`database` and `file` drivers; other drivers cannot be searched by account, and `complete` is `false` in the result) and always rotates the token.

Two ways to receive them:

1. Set `routes.events` to `/hop/events`. The package registers the route behind `hop.event`, which checks the signature with the same `HOP_SSO_SECRET` and `HOP_SSO_SECRET_PREV` as the sign-in tickets, accepts a timestamp within `events.max_age` seconds, and spends each nonce once. Every refusal is the same bare `401 {"ok":false,"error":"unauthorized"}`.
2. If the app already has a route with its own signature check, point it at `Hop\Auth\Http\Controllers\EventController` (a plain invokable controller). It checks the envelope, remembers each event id in the cache (a retry is answered `{duplicate: true}` and not handled twice) and runs the handlers. A handler that throws gives a 500 with no detail and the id is forgotten, so the hub's retry runs it again.

Add your own handlers in a service provider:

```php
use Hop\Auth\HopEvents;

HopEvents::on('account.deleted', fn (array $data, array $event) => $this->purge((int) $data['sub']));
HopEvents::on('account.status', function (array $data) {
    // return false when the event is not about anyone you know
});
HopEvents::forget('session.revoked'); // drop the built-in handler
```

The event ids need a cache store that survives between requests (`database`, `file`, `redis`), not `array`. The hub must have an `apps` row for the app with the `events` capability, or it sends nothing.

## Tests

The tests in `tests/Feature` are Pest tests that need a Laravel app with the shared `users` table, so they run inside an app that uses the package (HEL.ink includes a copy in `packages/auth`). This repository's own CI checks that `composer.json` is valid, that every PHP file lints under PHP 8.3, and that the family table below matches the canonical one.

## Security

Report vulnerabilities as described in [SECURITY.md](SECURITY.md), not in public issues.

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## Self-hosting

You do not install this package by hand to run HOP. LinkHop and BioHop list this public repository in their `composer.json`, so `composer install` fetches it with no token, and the hub ([HEL.ink](https://github.com/navi-crwn/HEL.ink)) carries a copy in `packages/auth`. An app that uses it needs a running hub on the same database and these settings: `HOP_HUB_URL`, `HOP_SSO_SECRET` (the hub's), `HOP_APP`, and on your own domain `HOP_TRUSTED_HOSTS`, because the default list holds only the hel.ink hosts. Imperium, the admin panel, is optional. The family guide covers the rest: [docs/self-hosting.md](https://github.com/navi-crwn/HEL.ink/blob/main/docs/self-hosting.md).

## HOP family

<!-- hop-family:start -->
<!-- Generated from HEL.ink resources/hop-assets/apps.source.json; edit there, then run scripts/hop_assets.py build. -->
| App | Address | Repository | What it does |
| --- | --- | --- | --- |
| **HOP ID** | [hel.ink](https://hel.ink) · accounts at `hub.hel.ink` | [navi-crwn/HEL.ink](https://github.com/navi-crwn/HEL.ink) | One account for every HOP app. |
| **LinkHop** | [link.hel.ink](https://link.hel.ink) · `hel.ink/l/…` | [navi-crwn/LinkHop](https://github.com/navi-crwn/LinkHop) | Short links, QR codes and click analytics. |
| **BioHop** | [bio.hel.ink](https://bio.hel.ink) · `hel.ink/b/…` | [navi-crwn/BioHop](https://github.com/navi-crwn/BioHop) | One link-in-bio page for everything you do. |
| **PixelHop** | [pixel.hel.ink](https://pixel.hel.ink) · `p.hel.ink/…` | [navi-crwn/PixelHop](https://github.com/navi-crwn/PixelHop) | Image hosting with albums and direct links. |
| **NoteHop** | [note.hel.ink](https://note.hel.ink) · `n.hel.ink/…` | [navi-crwn/NoteHop](https://github.com/navi-crwn/NoteHop) | Encrypted notes you share with a link. |
| **FileHop** | [file.hel.ink](https://file.hel.ink) | [navi-crwn/FileHop](https://github.com/navi-crwn/FileHop) | Convert files in your browser; nothing is uploaded. |
| **PayHop** | [pay.hel.ink](https://pay.hel.ink) | [navi-crwn/PayHop](https://github.com/navi-crwn/PayHop) | Make a payment QR and split a bill; nothing leaves your browser. |
| **Imperium** | not public-facing: optional, for admins | [navi-crwn/Imperium](https://github.com/navi-crwn/Imperium) | The HOP control panel. |
<!-- hop-family:end -->

## License

The HOP shared packages are open-source software licensed under the [MIT license](LICENSE).
