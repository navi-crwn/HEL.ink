# Changelog

All notable changes to HEL.ink (the HOP hub and HOP ID) are listed here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/). The `/changelog` page of the site shows the merged pull requests and commits of every public HOP app; it is generated from the git history by `scripts/build-changelog.py` into `resources/changelog.json`.

## [Unreleased]

### Changed

- Signing in no longer waits for outside services. `LoginRecorder` writes the login-history row and the account's last-sign-in fields at once (account, address, browser, how: password, Google or an app hand-off), and the GeoIP and proxy lookups that fill in the place and the proxy flags run as the queued job `EnrichLoginRecord` (the database queue served by `hub-queue`, with retries). If the queue cannot take the job, the lookups run after the response instead. The queue was chosen over `defer()` alone because a lookup that hangs would otherwise hold a PHP worker, and because it is retried. Turnstile verification stays in the sign-in request. All three callers (password, Google, single sign-on hand-off) go through `LoginRecorder`. Until the job has run, a new row shows no place.
- Signing out no longer walks the browser through every app. The hub queues `session.revoked` for every app that is configured and has the `events` capability, and those apps are left out of the redirect chain (`SsoController::logoutChain()` takes the slugs to skip). Only apps without `events` stay in the chain. If queuing throws, the full chain runs as before. The same path serves "Use a different account" on the two-factor page and `GET /logout` coming from an app (the hub redirects back to the allowed `return` address). Trade-off: an app's session now ends when the queued event arrives (normally a second or two) instead of during the redirect; if the queue is down, the apps' silent re-check (5 minutes) is the backstop. A `GET /logout` without a hub session queues no event, so it keeps the full chain.
- `SendHopEvent::broadcast()` returns the slugs of the apps whose job it queued.
- A sign-in lookup job that fails on its last attempt is dropped instead of being left in `failed_jobs` (its payload holds the sign-in address, and `failed_jobs` is outside the login-history retention and account deletion). The failure is logged without the address; the login row is already written. A worker killed by the job timeout is the one case that can still leave a `failed_jobs` row.
- An account event (including the `session.revoked` of a sign-out) that fails on every retry is counted per app, with the last time and type, and shown in `EventRegistry::warnings()`, which the admin users page and `hop:watch` already use. It holds no account id or address, and a later successful delivery clears it.

### Added

- Admin API for announcements (Imperium; signed, `admin_client`): `GET/POST /api/hop/announcements`, `GET/PUT /api/hop/announcements/{id}` and `POST /api/hop/announcements/{id}/actions` (`publish`, `unpublish`, `delete`), with the user endpoints' envelope, paging (`cursor`, `limit` up to 200, `page.total`), validation messages and `hop_audit` rows (`announcement.create`, `.update`, `.publish`, `.unpublish`, `.delete`). The model, the validation rules and the public display are unchanged, and the `/admin/announcements` pages work as before.
- User actions through the admin API now follow the hub's own rules, and fail closed on who acts: `suspend`, `delete` and `set_roles` need `X-HOP-Actor` to be `<id>:<username>` (or a plain id) of an existing account, otherwise 403 `forbidden` (`system` is refused for these three). `set_roles` refuses a list that grants `owner` (422) and is only allowed when `X-HOP-Actor` names an account that is an owner (403 `forbidden`); `suspend` and `delete` are refused (403 `forbidden`) when the actor is the target; `suspend` also deletes the account's API tokens, in one transaction with the status change. `GET /api/hop/users/{id}` adds `tokens` (id, name, last use and creation time; never a value or hash) and returns 10 logins instead of 5.
- Tests: the sign-in completes with every outside call failing and sends no lookup before the response; the sign-out chain leaves out apps with `events`, queues the event once per app, falls back to the full chain when the queue throws, and ends at the allowed `return` address; a sign-in started by an app returns to the app after a password sign-in, a password plus two-factor sign-in and a Google round trip.

## [1.2.0] - 2026-11-11

### Added

- PayHop (pay.hel.ink), the seventh HOP app. Registry: `apps.payhop`, `event_apps.payhop` (admin API, events), `registry_taglines`, `registry_repos`, `registry_logout`, `export_notes`, `PAYHOP_*` settings in `.env.example`, `phpunit.xml`, the seeder and the app factory; `github.repos`; the reserved names `pay`, `payhop` and `payhop-`. Pages: `/apps/payhop`, a home section with its 3D tile and a bill-split demo, the products and sitemap entries, FAQ, help guide, how-it-works, security, privacy and terms cards, in all nine languages. Its card on the dashboard shows no numbers (no `Stored: 0 B` line: it keeps saved payment methods for people who choose to save them).
- hop-assets v9: a copy of v8 plus PayHop's mark, PWA icons, 3D block map, floating tile (`.hop-theme-7`, eight tiles), name font (Unbounded, subset to "PayHop", with its OFL licence), home art and home demo. `apps.source.json` and the manifest are v9 with a `payhop` entry; v1 to v8 are unchanged. The family README table, `docs/hop-family.md` and the bundled manifest in `packages/ui` list PayHop.
- `scripts/check-hop-family.sh` takes `--ref <branch|sha>` (or `HOP_FAMILY_REF`) and `--from <file>` (or `HOP_FAMILY_FILE`); without them it behaves as before. The script is byte-identical in every HOP repository.
- Terms version 2026-11-11 (`resources/legal/terms-2026-11-11.md`) names FileHop and PayHop, with a section for each. It is registered in `config/legal.php` but not required: `LEGAL_TERMS_REQUIRED_VERSION` stays unset until the owner decides. The 2026-10-31 text is unchanged.
- `/changelog` reads `storage/app/changelog.json` when it exists and is usable, and the snapshot in the repository otherwise. `scripts/build-changelog.py --output` writes it, so a deploy can build the history without a pull request (`--check --output` verifies it).
- Tests: the v9 files and the PayHop block map equal the mark, v9 only differs from v8 in the five files PayHop needed, and the seeder and event tests use the list of event apps instead of a count.

- `php artisan hop:reset-2fa {email}`: the official way back in when an authenticator is lost. It clears the two-factor secret, recovery codes and confirmation of that account, refuses an unknown email, and writes an activity-log row without any secret.
- "Open Imperium" in the top bar for every account that holds a role (address from `HOP_IMPERIUM_URL`, then `HOP_CONTROL_URL`, default `https://imperium.hel.ink`; nine languages).
- The FileHop card shows "Uploads: Never" and "Stored: 0 B", so it is as tall as the other cards.
- Silent SSO check: `GET /sso/{app}` accepts `prompt=none`. With a hub session it issues the normal ticket; without one (or suspended, or a two-factor check open) it answers `302 <registered callback>?error=login_required&state=<state>` and shows no page; over the throttle it answers `error=temporarily_unavailable` the same way. Without `prompt=none` nothing changes (docs/silent-sso.md).

### Changed

- The "One account" scene on the home page is a globe you can turn: it spins slowly and follows a drag (`resources/js/home-globe.js`, two plain canvases, no new dependency). HOP ID is the core inside the globe, every app sits on its surface on its own curved line, a pulse runs along each line while its app is signed in, and the ticket shows a code that settles for each app. The sign-in code is a badge on the lower edge of the HOP ID mark. The story is one scrubbed function of the scroll position, so it plays the same forwards and backwards. Without scripts or with reduced motion the flat ring is shown as before.
- `packages/ui` is the hop-ui `feat/manifest-v9` branch (footer with the family row and the brand slot, `Apps` with the `pay` icon, tests); `packages/auth` is the hop-auth `feat/pay-host-silent-recheck` branch (`pay.hel.ink` trusted, silent account re-check that runs only while the hub's `/up` answers 2xx); the `packages/*/README.md` family tables list PayHop.
- Every `hop-assets/v8` reference (home, bento, account partial, tests, docs) is v9. The sentences that list the apps (FAQ, about, terms, open source, privacy) name PayHop, in nine languages; the nine READMEs list it (the German and Russian ones gained the family section), `install.sh`, `install-family.sh` (port 8105, Go and Node), `docs/self-hosting.md`, `docs/hop-assets.md` and `docs/adding-an-app.md` too.
- The GitHub Pages site (`docs/`) lists seven apps in nine languages and loads its fonts from hop-assets v9 instead of v1.
- The `/admin/users` pages stay: HOP ID's `set_roles` action and Imperium do not match them yet (Imperium lets any admin set roles and accepts `owner`; the hub page lets only the owner grant the four global roles and never `owner`, and refuses to suspend or delete the signed-in account).
- **Security rule change (owner account, made on 2026-11-01):** the owner account may now turn on two-factor, confirm it and renew its recovery codes for itself (`settings.2fa.enable`, `.confirm`, `.recovery-codes`), because Imperium refuses an account without it. Turning it off stays blocked for the owner, and every other read-only rule of the owner account is unchanged. Another account or an admin still cannot touch the owner's two-factor. `enable` is refused once 2FA is confirmed, for every account (turn it off first; the owner uses `hop:reset-2fa`), so a session and a password cannot move 2FA to another authenticator.
- The hub is for people and Imperium for admins: signing in always lands on the dashboard, the "Admin" chip and the sidebar "Admin" section (Accounts, Manage announcements) are gone, and an account with a role gets the same dashboard plus the one shortcut. The `/admin/*` pages, `Api/HopAdminController` and the `/hop/*` and `/api/hop/*` routes stay; the pages are simply no longer linked.
- Dashboard frames: every card, panel and box inside the hub layout has a 1 px line in the new `--hop-frame` token (the muted ink at 80% in light, 55% in dark, 3:1 or better against the page) and a more opaque surface; the bare bento grids of the dashboard no longer strip it; the app cards sit in equal columns and every gap is 16 px. The Clean style (`hop_ui`) uses the same token.
- "Open" on the FileHop card goes to the FileHop dashboard (`FILEHOP_OPEN_PATH`, default `/dashboard`) instead of the public page.

- hop-assets v6: new LinkHop and BioHop marks (LinkHop is the 8x8 pixel mark, BioHop the 9x9 one); `hop-float.js` and `hop-hero3d.js` draw both as pixel paths and the `home/linkhop-*.webp` art is re-rendered. The manifest, the hub pages and the tests point at it. v5 stays as it was.
- hop-assets v7: every visible hero scene that has not settled keeps drawing until it has (the scheduler drew only the four most visible, so a page with five family tiles never settled the fifth). Reduced motion still draws the final shape at once. v6 stays as it was.
- hop-assets v7 adds the shared bento: `hop-bento.css`, a six-column `.hop-bento` grid whose spans follow the box count so two to nine boxes always fill whole rows, and `hop-motion.js` with the staggered box reveal, the pointer spotlight and `is-live` on scenes only while they are on screen. The hub's home stats, FAQ, products, about, plans, help, contact and per-app pages use it, with small CSS scenes, and the dashboard grids use the same layout.
- hop-assets v7 logo rule: one logo, no text, for every app. The LinkHop and BioHop `wordmark` files are the same pixel mark as `mark`; the app name is real page text next to the mark.
- hop-assets v7 `hop-brand.css`: each app name in its own typeface, as ASCII-subset fonts with their OFL licences (HOP ID Syne, LinkHop Pixelify Sans, BioHop Space Grotesk, PixelHop Silkscreen, NoteHop Caveat, FileHop Sora).
- hop-assets v8: the bento is proportional. `hop-bento.css` has 60 desktop columns so five tiles share one row from 1100 px (`data-tiles`), no box is alone on a row or wider than half of it unless it is the only box or `data-span` asks for it, and media is capped (`[data-media]` 160-200 px, `[data-scene]` 140 px). Pattern tables for two to nine boxes are at the top of the file and `tests/hop-bento.test.mjs` checks them. The hub's home, products, about, help, plans and contact pages use it. v7 stays as it was.
- hop-assets v8: BioHop has a new mark, an 8x8 page with three lines, a stepped top-right corner and a cut bottom-left cell (`mark.svg`, `mark-white.svg`, the mark-only `wordmark.svg`, the 3D map and the floating tile). The manifest key `mark_white` is new.
- Home page: the three numbered eyebrow labels (01, 04, 05) are gone, and the copy no longer spells out how many apps there are ("Five apps", "all five apps", "five independent services" now read without the number, in all nine languages), so adding an app does not make the text wrong.
- `packages/safety` is the hop-safety v0.1.0 code (Safe Browsing v5, `LinkVerdict`, `Verdict`, its tests and fixtures).
- Public GitHub Pages docs: FileHop is listed in the app table and in the app lists of all nine languages.
- `scripts/build-changelog.py`: FileHop is a source of the public changelog.
- `robots.txt` names `https://hel.ink/sitemap.xml`.

### Added

- `GET /sitemap.xml` on the hub (it answered 404): the marketing pages and one product page per app. A test checks every `Sitemap:` line of `robots.txt` is served.
- Tests that fail when a local family list drifts from the hop-assets manifest: `FamilyStory::APPS`, `hop.github.repos` and `PUBLIC` in `scripts/build-changelog.py`.

### Fixed

- Status: "Failures in the last 24 hours" is one row per app with a status dot, the failure count, the last failure as relative time (full date on hover) and a 24-hour strip; a quiet empty state when there are none.
- Sign-out: `GET /logout` and `POST /logout` accept `return=<app home URL>` and end the chained logout there when its origin belongs to a configured family app (exact scheme, host and port); anything else falls back to the hub home. Apps send `{hub}/logout?return=<their home>` to end on the app the person left.
- Footer: the HOP brand strip is the first row inside the footer, below the top border and aligned with the first column; the language row is gone from the footer (the language menu is in the top bar) and the copyright line is right-aligned on desktop, centred on phones. `<x-hop::footer>` takes the strip as a `brand` slot and ignores `:languages`.
- The `/changelog` snapshot stopped at 2026-11-04 because `scripts/build-changelog.py` read the checkout's stale `origin/main` and never fetched. It now fetches first, and `scripts/build-changelog.py --repo HEL.ink --check` fails when merged pull requests are missing from `resources/changelog.json` (run it at release time, after rebuilding the snapshot). Snapshot regenerated.

## [1.1.0] - 2026-11-10

### Added

- hop-assets v5: `hop-ui.js` and `hop-ui.css`, shared dialogs (`HopUI.alert/confirm/prompt`) that replace the browser's own `alert`, `confirm` and `prompt` in every HOP app, and the "Hide · Shows again next week" button for reminder cards (`[data-hop-remind]`). Nine languages, keyboard first, a red button that names what is deleted, light or dark after the page, no motion under reduced motion. Laravel apps include it with `<x-hop::ui />`. v4 is unchanged.

### Changed

- Controls: the hub follows the HOP control standard (`--hop-ctl-*` tokens): one radius and one height for inputs, selects, buttons and search, capped field widths, 16 px text and 44 px touch targets on phones, no motion transforms under reduced motion. Applies to the dashboard, Settings, sign-in pages and admin.
- Hub UI: layered flat surfaces on the home page, dashboard, Settings, auth and admin, as an optional "Clean" interface style (Settings, Interface style). The HOP look stays the default.
- Dashboard: translated top bar title and "Deleted" badge, a drawn arrow on "Visit site", the wordmark opens the HOP ID dashboard, and the sidebar lists the HOP family.
- hop-assets v4: FileHop gets a pixel mark like NoteHop and PixelHop, the same 8x8 map as its 3D preset, its floating tile and its PWA icons (plus an Apple touch icon); the home page, `/apps/filehop` and the manifest use v4. v3 is unchanged.
- The HOP ID diagram on the home page shows every app on a ring around HOP ID (FileHop was missing; it showed four). Positions follow the number of apps, and the sign-in code no longer breaks over two lines on phones.
- Privacy: a FileHop section (what it keeps when you sign in, and that files never reach the server) and a line under retention. Terms: FileHop in the list of member apps and its own section. FAQ: FileHop in the deletion answer, and the languages answer lists the nine languages (it named eight, with Arabic).

### Added

- Dialogs: the hub asks through the shared HopUI dialog when it is on the page and falls back to the browser's own.
- Reminders on the dashboard: HOP ID's reminder cards can be hidden for a week.
- `docs/adding-an-app.md`: every place to change when an app joins the family.
- Translations of the FAQ, help, contact, about, app pages and status ticker texts that were shown in English in every language.

### Fixed

- Settings: `#security`, `#data` and `#tokens` open the tab they name, and on phones the active tab is brought into view.
- Registry: `/api/hop/apps` reports LinkHop's capabilities from its row instead of a list in the controller.
- Sign-in history keeps single sign-on hand-offs apart from real sign-ins.
- `GET /auth/google/consent` answers with the Google redirect while no terms version is required; it was a 500.
- The API page names the real API address and documentation of LinkHop and PixelHop, and no longer links pages that do not exist on BioHop and NoteHop.
- `/status` and `/status.json` answer while the database is down, with no numbers, as the home page already did. This holds when sessions are not stored in the database: with `SESSION_DRIVER=database` every web page still fails while the database is down.
- The `/goodbye` page follows the visitor's language and speaks of HOP, not LinkHop.
- The language is set first in the web middleware group, so the 419 notice, the real 429 page and the 404 of a missing record follow `?lang=` and the language cookie.
- A request value sent as an array (`?lang[]=de`, `?app[]=x`, `?state[]=x`, a form field `email[]=x`, a JSON object in the signed APIs) now counts as absent or fails validation, where it used to be a 500. This covers the language cookie, the changelog filter, the password reset page, single sign-on, the settings and admin pages, and the signed admin and account APIs.

### Security

- A profile photo over 50 megapixels is refused before it is decoded, and photo uploads are limited to 20 a minute.

## [1.0.0] - 2026-11-04

The first stable release. HEL.ink's first pull request was merged on 2026-10-28; 1.0.0 is the point where the hub and the contracts it keeps with the HOP apps are treated as the baseline that later releases build on. The history behind it is on the `/changelog` page and in the merged pull requests.

### Added

- **HOP ID.** One account for LinkHop, BioHop, PixelHop and NoteHop, with email sign-up and sign-in, Google sign-in, email verification, password reset, TOTP two-factor authentication with recovery codes, Cloudflare Turnstile, a profile with an avatar, and scoped API tokens.
- **Single sign-on.** The hub issues signed tickets that the apps verify (`hop/auth`). Two secrets can be active at once so a secret can be rotated without downtime, and signing out at the hub walks the chain of apps.
- **Account events to the apps.** The hub tells every app registered with the `events` capability when a profile changes (`profile.updated`), an account is suspended or restored (`account.status`), an account is deleted (`account.deleted`) and sessions are ended (`session.revoked`, also queued when a person signs out). Deliveries are signed and retried with backoff.
- **Admin API for Imperium.** A signed API (`/api/hop/*`) for an app registered with the `admin_client` capability: manifest, stats, users, per-user actions, audit log and search. Accounts with the `owner` role, and the configured superadmin email, are permanent superadmins that these actions refuse (`docs/PERMANENT-SUPERADMIN.md`). The hub also keeps its own user and announcement pages under `/admin` for admins.
- **Public site.** A home page that tells the story of the family with a shared 3D engine, pages for each app, terms, privacy, FAQ, help and about, a status page fed by a watchdog that checks every app, live repository numbers from GitHub, and a `/changelog` page built from a snapshot of the commit, tag and pull request history of the five public repositories.
- **Dashboard.** A launcher for the family, a card with numbers for each app, and the same chrome as the apps: a sidebar with the HOP family and a top bar for the primary action, language, theme and account menu.
- **HOP assets.** The family's marks, fonts and home engine are served from `hel.ink/hop-assets/` in immutable versions (`v1`, `v2`) with integrity hashes, and `scripts/hop_assets.py` builds and checks the manifest.
- **Shared packages.** `hop/auth`, `hop/ui` and `hop/safety` live in `packages/` and are synced into the apps.
- **Nine languages.** English, 简体中文, Deutsch, Bahasa Indonesia, 日本語, Português (Brasil), Español, Français and Русский, for the interface, the READMEs and the documentation site. Arabic and right-to-left layout are removed.
- **Self-hosting.** `install.sh`, `install-family.sh`, `update.sh` and `uninstall.sh`, a GitHub Pages documentation site and `SECURITY.md`.
- **Terms acceptance.** Optional, off until configured: new accounts can be asked to accept a versioned set of terms (`docs/legal-gate.md`).

### Security

- Signed-in pages are sent with `Cache-Control: no-store`, so Back after sign-out does not show them again.
- The onboarding routes require a signed-in account; recovery codes are compared strictly; the `_back` address is validated; the `mfa` claim in a ticket is tightened; and the public `status.json` carries only public fields.
- Sign-in history and account activity records are deleted after 90 days (`hop:prune-logs`, daily). Backup copies made by `scripts/backup.sh` are kept for 14 days on the same server, and the privacy page says so in all nine languages.
- Deleting an account purges the data held by the apps, and the deletion service checks that each app did it.
- `hop/safety` refuses links to encrypted NoteHop notes (the key is in the `#` part) and connects to the addresses it validated when it fetches a page.
- Pictures and fonts come from the hub's own host. Flags are country codes, not images from another site.

### Changed

- The 2026-11-04 audit fixes are included: error pages in the visitor's language, a language cookie that works with `SESSION_DOMAIN`, and `HopAppsSeeder`, which adds the `events` rows for the four apps without changing an existing row.

[Unreleased]: https://github.com/navi-crwn/HEL.ink/compare/v1.2.0...HEAD
[1.2.0]: https://github.com/navi-crwn/HEL.ink/releases/tag/v1.2.0
[1.1.0]: https://github.com/navi-crwn/HEL.ink/releases/tag/v1.1.0
[1.0.0]: https://github.com/navi-crwn/HEL.ink/releases/tag/v1.0.0
