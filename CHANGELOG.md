# Changelog

All notable changes to HEL.ink (the HOP hub and HOP ID) are listed here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/). The `/changelog` page of the site shows the merged pull requests and commits of every public HOP app; it is generated from the git history by `scripts/build-changelog.py` into `resources/changelog.json`.

## [Unreleased]

### Fixed

- The `/goodbye` page follows the visitor's language and speaks of HOP, not LinkHop.
- The language is set first in the web middleware group, so the 419 notice, the real 429 page and the 404 of a missing record follow `?lang=` and the language cookie.
- A request value sent as an array (`?lang[]=de`, `?app[]=x`, `?state[]=x`, a form field `email[]=x`, a JSON object in the signed APIs) now counts as absent or fails validation, where it used to be a 500. This covers the language cookie, the changelog filter, the password reset page, single sign-on, the settings and admin pages, and the signed admin and account APIs.

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

[Unreleased]: https://github.com/navi-crwn/HEL.ink/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/navi-crwn/HEL.ink/releases/tag/v1.0.0
