# hop/ui

Shared Blade UI components, navigation switchers, and icon assets for the **HOP** family of applications behind [hel.ink](https://hel.ink).

`hop/ui` provides standardized navigation, application switchers, and reusable UI components across all Laravel applications in the HOP ecosystem (`Hub`, `LinkHop`, `BioHop`).

---

## Features

- **Cross-App Navigation**: `<x-hop::nav-items />` component automatically renders links to sister applications configured in `config/hop.php`.
- **Reusable Blade Components**:
  - `<x-hop::pill />`: Status indicator pills with standardized semantic colors.
  - `<x-hop::badge />`: Compact role and count badges.
- **Application Registry Helper**: `Hop\Ui\Apps::all()` provides unified app metadata (slug, title, URL, icon SVG, theme color, current app indicator).
- **Theme Synchronization**: Designed to integrate seamlessly with the shared `localStorage.getItem('helink-theme')` ecosystem standard.

---

## Installation

Within a HOP ecosystem app, add the repository reference in `composer.json`:

```json
"repositories": [
    {
        "type": "vcs",
        "url": "https://github.com/navi-crwn/hop-ui.git"
    }
],
"require": {
    "hop/ui": "^0.1.0"
}
```

Then run:

```bash
composer update hop/ui
```

The `Hop\Ui\UiServiceProvider` will auto-register components under the `hop::` Blade namespace.

---

## Usage

### Cross-App Sidebar Navigation

In your application's layout file (e.g. `resources/views/layouts/app.blade.php`):

```blade
<nav class="sidebar-apps">
    <x-hop::nav-items />
</nav>
```

### Pills and Badges

```blade
<x-hop::pill color="emerald">Active</x-hop::pill>
<x-hop::pill color="amber">Suspended</x-hop::pill>

<x-hop::badge>2FA</x-hop::badge>
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
