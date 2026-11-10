# Credits

Third-party software and services used by the HEL.ink hub (hel.ink, hub.hel.ink).
This file lists what this repository ships or depends on directly. Transitive
dependencies are listed in `composer.lock` and `package-lock.json`, each with
its own license. The license of the hub itself is in `LICENSE`.

The notice in `LICENSE` also names components that the sibling apps use
(Leaflet, Chart.js, Figtree, FlagCDN, Redis, Sentry, GeoIP2). They are not part
of this repository; their credits are in the repository of the app that ships
them.

## Back end (Composer)

Runtime:

| Package | Version | License |
|---|---|---|
| laravel/framework | 12.x | MIT |
| laravel/socialite | 5.x | MIT |
| laravel/tinker | 2.x | MIT |
| pragmarx/google2fa-laravel | 2.x | MIT |
| simplesoftwareio/simple-qrcode | 4.x | MIT |
| propaganistas/laravel-disposable-email | 2.x | MIT |
| symfony/html-sanitizer | 7.x | MIT |
| matomo/device-detector | 6.x | LGPL-3.0-or-later (used unmodified, as a library) |

Development and tests only:

| Package | License |
|---|---|
| pestphp/pest, pestphp/pest-plugin-laravel | MIT |
| mockery/mockery | BSD-3-Clause |
| fakerphp/faker | MIT |
| nunomaduro/collision | MIT |
| laravel/pail | MIT |
| laravel/pint | MIT |

Local packages in `packages/` (`hop/auth`, `hop/safety`, `hop/ui`) are part of
the HOP family and under the same license as this repository.

## Front end (npm)

| Package | License | Use |
|---|---|---|
| alpinejs, @alpinejs/collapse | MIT | Interface behavior |
| axios | MIT | HTTP client |
| gsap (with ScrollTrigger) | GSAP "Standard no charge" license, https://gsap.com/standard-license | Scroll animation on the home page, bundled by Vite |
| lenis | MIT | Smooth scrolling on the home page, bundled by Vite |
| tailwindcss, @tailwindcss/forms, @tailwindcss/vite | MIT | Styles (build time) |
| vite, laravel-vite-plugin | MIT | Build tooling |
| concurrently | MIT | Development scripts |

## Fonts

Served from this site (`public/hop-assets/*/fonts/`), not from a third-party
CDN. All are under the SIL Open Font License 1.1; the license text of each is
kept next to the font files.

- Inter, by The Inter Project Authors
- Instrument Serif, by The Instrument Serif Project Authors
- JetBrains Mono, by The JetBrains Mono Project Authors
- Plus Jakarta Sans, by The Plus Jakarta Sans Project Authors
- Syne, by The Syne Project Authors
- Pixelify Sans, by The Pixelify Sans Project Authors
- Space Grotesk, by The Space Grotesk Project Authors
- Silkscreen, by The Silkscreen Project Authors
- Caveat, by The Caveat Project Authors
- Sora, by The Sora Project Authors

## Artwork

The HEL.ink rabbit, the HOP ID mark and the app logos and wordmarks
(`resources/brand/`, `public/hop-assets/`) are project artwork of HEL.ink.

The 3D logo scene on the home page (`hop-hero3d.js`) is plain WebGL written for
this project; no 3D library is loaded.

## Services

These are called at runtime when configured. No code or assets of them are
included here.

- Cloudflare Turnstile, bot check on sign-up, sign-in and password reset forms
  (Cloudflare terms)
- Google OAuth 2.0, optional "Sign in with Google" (Google API terms)
- IP geolocation, tried in order and skipped when not configured: a local
  `.mmdb` database if one is installed, then ipapi.co, ip-api.com, freeipapi.com,
  ipinfo.io and AbstractAPI (each under its own terms and quota)
- Proxy and VPN detection: proxycheck.io, IPHub, IPQualityScore, vpnapi.io,
  plus a built-in pattern check that needs no service

## Corrections

If an attribution is missing or wrong, write to admin@hel.ink.
