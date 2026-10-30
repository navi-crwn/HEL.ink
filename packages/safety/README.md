# hop/safety

Content and URL safety verification, threat feeds, and HTML sanitization for the **HOP** family of applications behind [hel.ink](https://hel.ink).

`hop/safety` protects the ecosystem against phishing, malware, malicious redirects, SSRF attempts, and XSS across short links (LinkHop) and bio pages (BioHop).

---

## Features

- **URL Guard (`UrlGuard`)**:
  - Restricts URL schemes to `http` and `https` (strictly blocks dangerous schemes like `javascript:`, `data:`, `file:`).
  - Protects against SSRF by disallowing private IP ranges, localhost, link-local, and loopback addresses.
- **Threat Detection (`LinkSafety`)**:
  - Checks target domains against the shared ecosystem database table `domain_blacklist`.
  - Integrates with external reputation feeds (URLhaus, PhishTank, Spamhaus DBL).
- **HTML Sanitization (`SafeContent`)**:
  - Sanitizes user-submitted rich content and markdown using Symfony HTML Sanitizer with strict safe element and attribute policies.
- **Threat Feed Sync (`abuse:feeds`)**:
  - Automated console command to keep local blacklist tables updated against real-time threat databases.

---

## Installation

Within a HOP ecosystem app, add the repository reference in `composer.json`:

```json
"repositories": [
    {
        "type": "vcs",
        "url": "https://github.com/navi-crwn/hop-safety.git"
    }
],
"require": {
    "hop/safety": "^0.1.0"
}
```

Then run:

```bash
composer update hop/safety
```

The `Hop\Safety\SafetyServiceProvider` will auto-register with Laravel package discovery.

---

## Usage

### Validating a Destination URL

```php
use Hop\Safety\LinkSafety;

if (LinkSafety::isBlocked($url)) {
    return back()->withErrors(['url' => 'Target domain is flagged as dangerous or malicious.']);
}
```

### Sanitizing Rich HTML / Markdown Content

```php
use Hop\Safety\SafeContent;

$cleanHtml = SafeContent::sanitize($rawUserHtml);
```

### Updating Threat Feeds

Run via cron or task scheduler:

```bash
php artisan abuse:feeds
```

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
