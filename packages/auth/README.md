# hop/auth

Single Sign-On (SSO) client, ticket verification, and request signing package for the **HOP** family of applications behind [hel.ink](https://hel.ink).

`hop/auth` enables any Laravel application in the ecosystem to authenticate members against the centralized HOP ID hub (`hub.hel.ink`), verify short-lived HMAC tickets, validate scoped API tokens, handle chained logouts, and verify inter-service server-to-server HMAC signatures.

---

## Features

- **SSO Ticket Verification**: Validates short-lived tickets issued by HOP ID (`/auth/sso/callback`).
- **Dual-Secret Rotation**: Seamlessly transitions secrets (`HOP_SSO_SECRET` and `HOP_SSO_SECRET_PREV`) with zero user-facing downtime during secret rotations.
- **Scoped API Tokens**: Middleware `hop.token:{scope}` verifying tokens against the shared `api_tokens` table.
- **Chained Logout**: Cleanses local app session and forwards logout request to the hub logout endpoint.
- **Signed Inter-Service Calls**: Middleware `hop.signed` verifying `X-HOP-Signature`, `X-HOP-Nonce`, and anti-replay timestamp bounds.

---

## Installation

Within a HOP ecosystem app, add the repository reference in `composer.json`:

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

The `Hop\Auth\AuthServiceProvider` will auto-register with Laravel package discovery.

---

## Configuration

Add the following environment variables to your application's `.env`:

```ini
HOP_APP=linkhop
HOP_HUB_URL=https://hub.hel.ink
HOP_SSO_SECRET=current_secret_key_minimum_32_characters
HOP_SSO_SECRET_PREV=previous_secret_key_during_rotation_window
```

### Registered Middleware Aliases

- `hop.auth`: Ensures the user is authenticated via HOP SSO; redirects unauthenticated visitors to `HOP_HUB_URL/auth/sso`.
- `hop.token:{scope}`: Verifies bearer API tokens for specified scopes.
- `hop.signed`: Verifies server-to-server signed requests (HMAC-SHA256).

---

## HOP Family Ecosystem

| App | Live Address | Repository | Purpose |
| --- | --- | --- | --- |
| **Hub** | `hel.ink` · `hub.hel.ink` | [navi-crwn/HEL.ink](https://github.com/navi-crwn/HEL.ink) | Account portal, HOP ID, SSO issuer, launcher, admin |
| **LinkHop** | `link.hel.ink` · `hel.ink/l/{slug}` | [navi-crwn/LinkHop](https://github.com/navi-crwn/LinkHop) | URL shortener, QR codes, click analytics, API |
| **BioHop** | `bio.hel.ink` · `hel.ink/b/{slug}` | [navi-crwn/BioHop](https://github.com/navi-crwn/BioHop) | Link-in-bio pages with custom blocks, themes, stats |
| **PixelHop** | `p.hel.ink` (soon `pixel.hel.ink`) | [navi-crwn/PixelHop](https://github.com/navi-crwn/PixelHop) | Image & media hosting, EXIF/GPS scrubbing, studio |
| **NoteHop** | `note.hel.ink` | [navi-crwn/NoteHop](https://github.com/navi-crwn/NoteHop) | Zero-knowledge client-encrypted live notes & docs |
| **Imperium** | Internal / Tailscale | Private | Unified admin & operations console across all apps |

---

## License

The HOP shared packages are open-source software licensed under the [MIT license](../../LICENSE).
