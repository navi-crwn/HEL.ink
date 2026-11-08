# Self-hosting the HOP family

Every piece of HOP is in a public repository under [github.com/navi-crwn](https://github.com/navi-crwn). You need no private repository, no access token and no account on hel.ink. Run one app on its own, or the whole family on your own domain.

The short version for one computer or a small server:

```bash
curl -fsSL https://raw.githubusercontent.com/navi-crwn/HEL.ink/main/install-family.sh | bash
```

It installs the hub and the apps you pick, each in its own folder, already connected. The rest of this page explains what it does, so you can also do it by hand.

## The repositories

| Repository | What it is | Built with |
| --- | --- | --- |
| [navi-crwn/HEL.ink](https://github.com/navi-crwn/HEL.ink) | The hub: HOP ID accounts, sign-in for every app, the launcher, the family assets | PHP 8.3, Laravel, Node 20.19+ for the build |
| [navi-crwn/LinkHop](https://github.com/navi-crwn/LinkHop) | Short links, QR codes, click analytics | PHP 8.3, Laravel, Node |
| [navi-crwn/BioHop](https://github.com/navi-crwn/BioHop) | Link-in-bio pages | PHP 8.3, Laravel, Node |
| [navi-crwn/PixelHop](https://github.com/navi-crwn/PixelHop) | Image hosting | Go 1.27, or Docker, or a release binary |
| [navi-crwn/NoteHop](https://github.com/navi-crwn/NoteHop) | Encrypted notes | Go 1.27, or Docker |
| [navi-crwn/FileHop](https://github.com/navi-crwn/FileHop) | File conversion in the browser | Go 1.27 and Node 20.19+, or Docker |
| [navi-crwn/Imperium](https://github.com/navi-crwn/Imperium) | The admin panel for the whole family (optional) | Go 1.27 |
| [navi-crwn/hop-auth](https://github.com/navi-crwn/hop-auth), [hop-ui](https://github.com/navi-crwn/hop-ui), [hop-safety](https://github.com/navi-crwn/hop-safety) | Shared PHP packages for the Laravel apps | Composer installs them for you |

## What each app needs

| App | HOP ID hub | Database | Imperium | Runs alone? |
| --- | --- | --- | --- | --- |
| Hub (HEL.ink) | is the hub | SQLite, MySQL or MariaDB | optional | yes: accounts and the launcher |
| LinkHop | **required**: accounts live in the hub | **the hub's database** | optional, but its admin pages live there | no |
| BioHop | **required**: accounts live in the hub | **the hub's database** | optional, but its admin pages live there | no |
| PixelHop | optional: "Sign in with HOP ID" | its own SQLite | optional, but its admin pages live there; needs the hub | yes, with its own accounts |
| NoteHop | optional: "Sign in with HOP ID" | its own SQLite | optional; needs the hub | yes, with its own accounts |
| FileHop | optional: "Sign in with HOP ID" | its own SQLite | optional; needs the hub | yes, with no accounts at all |
| Imperium | **required**: sign-in and the list of apps come from the hub | its own SQLite | is the admin panel | no |

LinkHop and BioHop share the hub's database: the hub owns the account tables (`users`, `api_tokens`, …), and each app runs only its own migrations, each with its own migrations table (`DB_MIGRATIONS_TABLE`).

### Shared pieces

- **hop/auth, hop/ui, hop/safety.** LinkHop and BioHop list these public GitHub repositories in `composer.json`, so `composer install` fetches them with no token. The hub carries its own copies under `packages/`.
- **Family assets (fonts, the floating app tiles, app marks).** The hub serves them under `/hop-assets/`. The apps link to the copies on `https://hel.ink/hop-assets/` with integrity hashes and also bundle the manifest, so nothing has to be installed for them. If a visitor's browser cannot reach hel.ink, pages still work, with system fonts and without the floating tiles.
- **Imperium.** The admin panel for the whole family: users, content, abuse reports, settings, health, backups and error reports. LinkHop, BioHop and PixelHop moved their admin pages there. Without Imperium their `/admin` shows a page that says so. You can still manage accounts in the hub's own admin pages (`/admin` on the hub) and use each app's command line (`php artisan …`, `pixelhop user add …`, `notehop takedown …`) and signed API. Imperium is only useful together with a hub.

## Install order

1. **The hub.** `install.sh` in HEL.ink, or by hand: `composer install`, `.env`, `php artisan key:generate`, `php artisan migrate`, `npm ci && npm run build`. Then `php artisan hop:owner you@example.org`: that address becomes the owner when it signs up.
2. **The apps.** Each repository has an `install.sh` that can connect to your hub (`--hub existing --hub-url … --hub-secret …`) or install one next to it (`--hub new`).
3. **Register the apps at the hub.** Set each app's address in the hub's `.env` (`LINKHOP_URL`, `BIOHOP_URL`, `PIXELHOP_URL`, `NOTEHOP_URL`, `FILEHOP_URL`), then run `php artisan optimize` and `php artisan db:seed --class='Database\Seeders\HopAppsSeeder' --force` in the hub. That adds a registry row for every app, with the capabilities it needs: account events, its own sign-in form, the admin API for Imperium. It never changes a row that exists. `install-family.sh` does this for you.
4. **Imperium (optional).** See below.

## The minimum settings

Secrets are placeholders here. Make each one with `openssl rand -hex 32`.

**Hub (`.env`)**

```ini
APP_URL=https://hub.example.org
HUB_HOST=hub.example.org
HOP_SSO_SECRET=<32+ random characters, shared with LinkHop and BioHop>
DB_CONNECTION=mysql            # or sqlite
DB_MIGRATIONS_TABLE=migrations_hub
LINKHOP_URL=https://link.example.org
BIOHOP_URL=https://bio.example.org
PIXELHOP_URL=https://pixel.example.org
NOTEHOP_URL=https://note.example.org
FILEHOP_URL=https://file.example.org
HOP_SSO_SECRET_NOTEHOP=<NoteHop's NOTEHOP_SSO_SECRET>
HOP_SSO_SECRET_FILEHOP=<FileHop's FILEHOP_SSO_SECRET>
# Only with Imperium:
HOP_CONTROL_URL=https://imperium.example.org
HOP_SSO_SECRET_IMPERIUM=<Imperium's HOP_SSO_SECRET>
```

**LinkHop and BioHop (`.env`)**

```ini
APP_URL=https://link.example.org        # BioHop: https://bio.example.org
PUBLIC_URL=https://link.example.org     # where short links and bio pages are reached
HOP_APP=linkhop                         # BioHop: biohop
HOP_HUB_URL=https://hub.example.org
HOP_SSO_SECRET=<the hub's HOP_SSO_SECRET>
DB_*=<the hub's database>
DB_MIGRATIONS_TABLE=migrations          # BioHop: migrations_bio (the hub keeps migrations_hub)
HOP_TRUSTED_HOSTS=hub.example.org,link.example.org,bio.example.org
HOP_CONTROL_URL=https://imperium.example.org   # only with Imperium
```

`HOP_TRUSTED_HOSTS` matters on your own domain: by default hop/auth trusts only the hel.ink hosts for the sign-out chain.

**PixelHop, NoteHop, FileHop (environment)**

```ini
PIXELHOP_BASE_URL=https://pixel.example.org
# with the hub: HELINK_URL=https://hub.example.org and HELINK_SSO_SECRET=<the hub's HOP_SSO_SECRET>
# with Imperium: PIXELHOP_CONTROL_URL=https://imperium.example.org

NOTEHOP_BASE_URL=https://note.example.org
# with the hub: NOTEHOP_SSO_URL=https://hub.example.org, NOTEHOP_SSO_CLIENT=notehop, NOTEHOP_SSO_SECRET=<HOP_SSO_SECRET_NOTEHOP>

FILEHOP_BASE_URL=https://file.example.org
# with the hub: FILEHOP_SSO_URL=https://hub.example.org, FILEHOP_SSO_CLIENT=filehop, FILEHOP_SSO_SECRET=<HOP_SSO_SECRET_FILEHOP>
```

Each app's README and `.env.example` (or `deploy/*.env.example`) list every other setting.

## Imperium

Imperium signs in through HOP ID and reads the list of apps, and their admin secrets, from the hub. Install it after the hub and the apps.

```bash
git clone https://github.com/navi-crwn/Imperium.git && cd Imperium
make build                     # bin/imperium; needs Go 1.27+
```

Its environment:

```ini
IMPERIUM_ADDR=127.0.0.1:8100
IMPERIUM_DB=/var/lib/imperium/imperium.db
IMPERIUM_BASE_URL=https://imperium.example.org
HOP_URL=https://hub.example.org
HOP_SSO_CLIENT=imperium
HOP_SSO_SECRET=<32+ random characters; the hub's HOP_SSO_SECRET_IMPERIUM>
IMPERIUM_REQUIRE_MFA=1         # admins need two-factor sign-in at the hub
```

At the hub, set `HOP_CONTROL_URL` and `HOP_SSO_SECRET_IMPERIUM`, then run the seeder from step 3: it registers Imperium as a hidden app that may call the admin API. In LinkHop and BioHop set `HOP_CONTROL_URL`, in PixelHop `PIXELHOP_CONTROL_URL`, so their `/admin` sends admins to Imperium. Only accounts with an admin role at the hub get in.

Imperium is a control panel: keep it off the open internet (a VPN such as Tailscale or WireGuard, or an access proxy) and serve it over HTTPS. `deploy/imperium.service` is a hardened systemd unit you can reuse. The other files in `deploy/` and `scripts/` (nginx for `imperium.hel.ink`, the Cloudflare tunnel, `tahap*.sh`, `deploy-*.sh`, `hop-backup.sh`) are the hel.ink production setup: read them as examples, not as an installer.

## Running one app, or the family

- **One app.** NoteHop, PixelHop and FileHop run alone with `./install.sh` (the default is no hub). LinkHop and BioHop need a hub; `./install.sh --hub new` installs one next to them.
- **The family.** `install-family.sh` installs the hub and the apps you choose under one folder and writes `start-all.sh`. On a server, put each app behind your web server on its own host name, and use the same `HOP_SSO_SECRET` in the hub, LinkHop and BioHop.
- **Updates.** Each app has `./update.sh`; the hub and the Laravel apps run their migrations on update.
