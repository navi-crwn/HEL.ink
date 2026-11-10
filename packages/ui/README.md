# hop/ui

**English** · [简体中文](README.zh-CN.md) · [Deutsch](README.de.md) · [Bahasa Indonesia](README.id.md) · [日本語](README.ja.md) · [Português (Brasil)](README.pt-BR.md) · [Español](README.es.md) · [Français](README.fr.md) · [Русский](README.ru.md)

The shared Blade components and family data for the Laravel apps of the HOP family ([hel.ink](https://hel.ink)): the family navigation, the language, app and account menus, the footer, the typography and floating tiles, and the one list of supported languages. Every app that uses it looks and behaves the same in those places.

## Screenshots

These come from the BioHop dashboard and home page, run locally with a demo account, using this package at `main`.

![The BioHop sidebar with the HOP family list: HOP ID, LinkHop, PixelHop, NoteHop and FileHop, each with its mark. BioHop itself is not listed.](docs/images/family-nav.png)

![The language menu in the top bar, open: nine languages with the native name on the left and a short code on the right.](docs/images/language-switcher.png)

![The account menu in the top bar, open: the demo user's initial, name and email, then Account (HOP ID), Settings and Sign out.](docs/images/account-menu.png)

![The app switcher in the site header, open: HOP ID, LinkHop, BioHop, PixelHop and NoteHop, with BioHop marked as the current app.](docs/images/app-switcher.png)

## Requirements

- PHP 8.3 and Laravel 12 (`illuminate/support` ^12.0).
- A shared `config/hop.php` in the app (see below).
- Tailwind CSS in the app. `pill`, `badge`, `nav-items`, `family-nav` and `footer` are written with Tailwind utility classes (and `dark:` variants for the `dark` class), and `nav-items` and `family-nav` use the app's own `nav-item` class, so the app's Tailwind build has to scan this package's views. The three menus (`language-switcher`, `app-switcher`, `account-menu`) carry their own CSS and JavaScript. `family` and `back` have no styles; the app styles `hop-family-*` and `hop-back`.
- Network access to `hel.ink` in the browser. Fonts, floating tiles and the app marks load from `https://hel.ink/hop-assets/`, with subresource integrity hashes, so the app's Content Security Policy must allow that host.

## Installation

The package is not on Packagist. Add the repository to the app's `composer.json`:

```json
"repositories": [
    {
        "type": "vcs",
        "url": "https://github.com/navi-crwn/hop-ui.git"
    }
],
"require": {
    "hop/ui": "^0.2.1"
}
```

Then run:

```bash
composer update hop/ui
```

The latest tag, `v0.2.1`, does not contain everything described here. `family-nav`, `account-menu`, `app-switcher`, `language-switcher`, `footer` and the `Locales` list were added after it. Until the next tag, require `dev-main` (the apps pin a commit of `main`).

`Hop\Ui\UiServiceProvider` is discovered automatically. It registers the views under the `hop::` namespace and makes the components available as `<x-hop::name />`. To override the views, publish them:

```bash
php artisan vendor:publish --tag=hop-ui-views
```

## What the app provides

The navigation reads the app list from `config/hop.php`:

```php
return [
    'current' => 'biohop',                 // this app's slug; 'hub' in the hub
    'hub_url' => 'https://hub.hel.ink',
    'apps' => [
        'linkhop' => [
            'name' => 'LinkHop',
            'url' => 'https://link.hel.ink',
            'icon' => 'link',              // hop, link, bio, image, note or globe
            'color' => 'indigo',
            'start' => '/auth/sso',        // optional; opens the app through its SSO start
            'enabled' => true,             // optional; false hides the app
        ],
    ],
];
```

The package ships no translations. The strings it passes through `__()` (such as `HOP Family`, `Language`, `Account`, `Sign out`, `Settings`, `Changelog`) are translated by the app's own language files, and a few strings are plain English: the default `a HOP app` of `badge`, the `Back` of `back`, and the default title and intro of `family`.

## Components

| Component | Props | What it renders |
| --- | --- | --- |
| `<x-hop::fonts />` | none | In `<head>`: the family typography (Inter, Instrument Serif and JetBrains Mono) from hel.ink. Use `var(--hop-font-display)`, `var(--hop-font-body)`, `var(--hop-font-serif)` and `var(--hop-font-mono)`. |
| `<x-hop::float />` | `mode`: `fixed` (default) or `section` | The floating HOP tiles, drawn by hel.ink's `hop-float.js`. `section` fills a `position: relative` box. |
| `<x-hop::ui />` | none | Once in the layout, after the fonts: hel.ink's `hop-ui.js` and `hop-ui.css`. `HopUI.alert`, `HopUI.confirm` and `HopUI.prompt` replace the browser's own dialogs (Promises, keyboard first, nine languages, a red button that names what is deleted); `[data-hop-remind]` cards get a "Hide · Shows again next week" button that posts the key to `data-hop-remind-url`. |
| `<x-hop::family />` | `current`, `title`, `intro` | A section listing every public app with its mark and tagline. The current app is marked and not linked. |
| `<x-hop::back />` | `href` (default: the app's home), `label` | A back link for inner pages. |
| `<x-hop::badge />` | slot | A small pill linking to the hub. It reads `a HOP app` unless you give it other text. |
| `<x-hop::pill />` | none | A row of pills, one per app from `config/hop.php` plus the hub, with the current app filled. |
| `<x-hop::nav-items />` | `except`: slugs to leave out | List items for the other apps, for an existing `<ul>`. |
| `<x-hop::family-nav />` | `app`, `label` | The "HOP family" block for a dashboard sidebar: HOP ID first, then the other public apps with their marks. The current app is left out. |
| `<x-hop::app-switcher />` | `app` | A dropdown with every public app. The current one has `aria-current`. |
| `<x-hop::language-switcher />` | none | A dropdown with the nine languages. Choosing one adds `?lang=<code>` to the current URL. |
| `<x-hop::account-menu />` | `user`, `settings-url`, `account-url`, `logout-url` | A dropdown with the person's initial, name and email, and links for HOP ID, settings and a sign-out form. A link or the form is left out when its URL is not given. |
| `<x-hop::footer />` | `app`, `name`, `repo`, `features-url`, `status-url`, `abuse-url` | The columnar footer: the app's links, support, policies, and the HOP family list. An optional `brand` slot replaces the "Part of Hop Easy Link" row at the top. The `languages` prop is deprecated and ignored (the language menu lives in the top bar). |

The dashboard layout the family uses puts the primary action, the language menu, the theme toggle and the account menu in the top bar, and `family-nav` in the sidebar. The footer links to `/about`, `/changelog`, `/help`, `/faq`, `/contact`, `/terms` and `/privacy` on the app's own domain, so the app has to serve those paths.

```blade
<head>
    <x-hop::fonts />
</head>
<aside>
    <x-hop::family-nav app="biohop" />
</aside>
<header>
    <x-hop::language-switcher />
    <x-hop::account-menu :user="$user" :settings-url="route('settings')" :account-url="$hubSettingsUrl" :logout-url="route('logout')" />
</header>
```

The menus close on Escape and on a click outside, and the arrow, Home and End keys move through their links. Their styles and script are included once per page, however many menus there are, and use the CSP nonce from `Vite::cspNonce()` when the app sets one.

## PHP classes

### `Hop\Ui\Apps`

`Apps::all()` returns the hub and the enabled apps of `config/hop.php` as arrays with `slug`, `name`, `url`, `open`, `icon`, `color` and `current`. `open` is where a link should go: from the hub it is `{hub}/apps/{slug}`, from an app it is the app's URL plus its `start` path (`/auth/sso` by default). `Apps::icon($name)` returns the SVG path of an icon, and falls back to `globe`.

### `Hop\Ui\Assets`

The family asset manifest lists the fonts, the floating tiles and every app's marks, each with an integrity hash.

- `Assets::manifest()` reads `https://hel.ink/hop-assets/apps.json` with a 2 second timeout and caches it for 5 minutes (cache key `hop-ui:assets`), so a new asset version reaches every app without a release. If hel.ink cannot be reached, or sends something unusable, it uses the copy bundled in `resources/hop-assets/apps.json`. A failed request is logged as a warning.
- `Assets::family($current)` returns the public apps in family order with the current one marked. `Assets::asset($key)` returns the URL and integrity hash of `fonts_css`, `float_css` or `float_js`.
- Config: `hop-ui.assets.remote` turns the remote read off (it is off by default while unit tests run), and `hop-ui.assets.url` changes the address. There is no config file to publish; set these in a config file of your own named `hop-ui.php`.
- `scripts/sync-hop-assets.sh [path to a HEL.ink checkout]` refreshes the bundled copy from a checkout, or from hel.ink when no path is given.

### `Hop\Ui\Locales`

The one list of languages every HOP app ships: English `en`, 简体中文 `zh-CN`, Deutsch `de`, Bahasa Indonesia `id`, 日本語 `ja`, Português (Brasil) `pt-BR`, Español `es`, Français `fr` and Русский `ru`. Right-to-left languages are not part of the set.

- `Locales::ALL` maps each code to its native `name` and a `short` label. `codes()` and `names()` give the codes and the native names, and `short($code)` the label.
- `Locales::resolve($value)` returns a supported code or `null`. It maps the older `zh` to `zh-CN` and `pt` to `pt-BR`, and ignores case.
- `Locales::normalize($value)` is `resolve()` with a fallback: anything empty, unknown or retired, such as `ar`, becomes `en`.

The language switcher is drawn from this list.

## Tests

The tests in `tests/Feature` are Pest tests that run inside an app that uses the package (HEL.ink includes a copy in `packages/ui`). This repository's own CI checks that `composer.json` is valid, that every PHP file lints under PHP 8.3, and that the family table below matches the canonical one.

## Security

Report vulnerabilities as described in [SECURITY.md](SECURITY.md), not in public issues.

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## Self-hosting

You do not install this package by hand to run HOP. LinkHop and BioHop list this public repository in their `composer.json` (pinned to a commit of `main`), so `composer install` fetches it with no token, and the hub ([HEL.ink](https://github.com/navi-crwn/HEL.ink)) carries a copy in `packages/ui`. On your own domain, keep two things in mind: the fonts, floating tiles and app marks still load from `https://hel.ink/hop-assets/` (allow that host in your Content Security Policy; without it pages fall back to system fonts and show no tiles), and the family list comes from hel.ink's manifest unless you set `hop-ui.assets.url`, or turn the remote read off with `hop-ui.assets.remote` to use the bundled copy. The family guide covers the rest: [docs/self-hosting.md](https://github.com/navi-crwn/HEL.ink/blob/main/docs/self-hosting.md).

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
