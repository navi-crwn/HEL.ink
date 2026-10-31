<p align="center">🌐 <b>English</b> · <a href="README.zh.md">简体中文</a> · <a href="README.es.md">Español</a> · <a href="README.ar.md">العربية</a> · <a href="README.pt.md">Português</a> · <a href="README.fr.md">Français</a> · <a href="README.ja.md">日本語</a> · <a href="README.id.md">Bahasa Indonesia</a></p>

# HEL.ink — The HOP Family Hub & HOP ID

<p align="center">
  <a href="LICENSE"><img src="https://img.shields.io/badge/license-MIT-blue.svg" alt="MIT license"></a>
  <a href="https://github.com/navi-crwn/HEL.ink/actions/workflows/tests.yml"><img src="https://github.com/navi-crwn/HEL.ink/actions/workflows/tests.yml/badge.svg?branch=main" alt="tests workflow"></a>
</p>

<p align="center"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/readme/hero-en-dark.webp"><img src="docs/readme/hero-en-light.webp" alt="HEL.ink: the central account portal and hub for the HOP family" width="100%"></picture></p>

<p align="center"><b>One HOP account for every app — sign in once and move freely across the ecosystem.</b></p>

<p align="center">
  <a href="https://hel.ink">hel.ink</a> ·
  <a href="https://hub.hel.ink">hub.hel.ink</a> ·
  <a href="#run-your-own">Run your own</a> ·
  <a href="#features">Features</a> ·
  <a href="#faq">FAQ</a> ·
  <a href="#security">Security</a> ·
  <a href="#license">License</a>
</p>

---

## What is HEL.ink?

**HEL.ink** (Hop Easy Link) is the home and account portal of the **HOP** family of privacy-respecting, self-hostable web applications.

This repository serves as the **ecosystem hub**:
1. **Public home page (`hel.ink`)**: Introduces the family, guides new users, and hosts centralized family branding and typography assets.
2. **Account portal (`hub.hel.ink`)**: Issues signed single sign-on (SSO) tickets for member apps, manages HOP ID user profiles, 2FA, password resets, and session termination.
3. **Shared packages (`hop-auth`, `hop-ui`, `hop-safety`)**: Provides foundational authentication, UI layouts, navigation switchers, and threat scanning across all Laravel-based HOP apps.

<a id="features"></a>

## Features

- **🔐 Single Sign-On (HOP ID)**: One account signs you into LinkHop, BioHop, PixelHop, and NoteHop without repetitive credentials. Signed cryptographic tickets and chained session termination.
- **🚀 Unified App Launcher**: An integrated top-level switcher letting users jump between all family apps seamlessly.
- **🛡️ Comprehensive Account Security**: Argon2id password hashing, TOTP two-factor authentication, Cloudflare Turnstile anti-bot verification, and secure email verification.
- **🗄️ Shared Identity Database**: Single MySQL or SQLite identity backend with isolated per-app migration tables (`migrations_hub`, `migrations_bio`, etc.) to prevent schema pollution.
- **📡 Live Service Watchdog**: Scheduled background health checks and latency monitoring for all member apps with zero credential exposure in public status JSON.
- **🎨 Central Asset Delivery**: Official marks, Syne/Plus Jakarta Sans/JetBrains Mono fonts, and floating 3D canvas tiles served with SRI hashes from `hel.ink/hop-assets/v1/`.
- **🔑 Fine-Grained API Tokens**: Scoped tokens (OAuth-style) enabling secure inter-app automation and developer access.
- **🌐 100% Free & Open Source**: MIT licensed, no telemetry, no paywalls, completely self-hostable.

<a id="run-your-own"></a>

## Run your own

Prerequisites: PHP 8.3+, Composer, Node.js 22+, and SQLite or MySQL/MariaDB.

```bash
git clone https://github.com/navi-crwn/HEL.ink.git
cd HEL.ink
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate
npm install && npm run build
php artisan serve
```

### Configuration

Open `.env` (detailed in `.env.example`) to configure essential settings:

| Key | Description |
|---|---|
| `APP_URL` | Public front-facing address (`https://hel.ink`) |
| `HUB_HOST` | Hostname for the accounts portal (`hub.hel.ink`) |
| `HOP_SSO_SECRET` | Secret key (32+ random characters) for signing SSO tickets |
| `DB_CONNECTION`, `DB_*` | Database connection settings |
| `DB_MIGRATIONS_TABLE` | Keep set to `migrations_hub` to isolate the hub's migration history |

Run tests:
```bash
php artisan test
```

<a id="faq"></a>

## FAQ

**What is HOP ID?**
HOP ID is the central identity provider for the HOP family. Creating one account lets you sign into LinkHop, BioHop, PixelHop, and NoteHop with zero friction.

**Can I run the hub without other apps?**
Yes. You can run the hub standalone as a directory and user account portal, but it shines best when powering member apps.

**How does logout work across apps?**
When you log out from the hub, your session is terminated locally and chained logout requests are dispatched to connected applications.

**Can I host everything on one VPS?**
Yes. All HOP applications are designed to share a database and live harmoniously on a single server or container environment.

<a id="family"></a>

## The HOP family

HEL.ink is part of the **HOP** family of self-hostable open-source apps behind [hel.ink](https://hel.ink).

<!-- hop-family:start -->
<!-- Generated from HEL.ink resources/hop-assets/apps.source.json; edit there, then run scripts/hop_assets.py build. -->
| App | Address | Repository | What it does |
| --- | --- | --- | --- |
| **HOP ID** | [hel.ink](https://hel.ink) · accounts at `hub.hel.ink` | [navi-crwn/HEL.ink](https://github.com/navi-crwn/HEL.ink) | One account for every HOP app. |
| **LinkHop** | [link.hel.ink](https://link.hel.ink) · `hel.ink/l/…` | [navi-crwn/LinkHop](https://github.com/navi-crwn/LinkHop) | Short links, QR codes and click analytics. |
| **BioHop** | [bio.hel.ink](https://bio.hel.ink) · `hel.ink/b/…` | [navi-crwn/BioHop](https://github.com/navi-crwn/BioHop) | One link-in-bio page for everything you do. |
| **PixelHop** | [pixel.hel.ink](https://pixel.hel.ink) · `p.hel.ink/…` | [navi-crwn/PixelHop](https://github.com/navi-crwn/PixelHop) | Image hosting with albums and direct links. |
| **NoteHop** | [note.hel.ink](https://note.hel.ink) · `n.hel.ink/…` | [navi-crwn/NoteHop](https://github.com/navi-crwn/NoteHop) | Encrypted notes you share with a link. |
| **Imperium** | private (owner only) | private | The HOP control panel. |
<!-- hop-family:end -->

<a id="security"></a>

## Security

Found a vulnerability? Please report it privately through GitHub's security advisories: [github.com/navi-crwn/HEL.ink/security/advisories](https://github.com/navi-crwn/HEL.ink/security/advisories). Do not open public issues for security vulnerabilities. See [SECURITY.md](SECURITY.md) for full details.

<a id="license"></a>

## License

[MIT](LICENSE) — free and open source.

---

<p align="center"><sub>HEL.ink · HOP ID · part of the HOP family · <a href="https://hel.ink">hel.ink</a></sub></p>
