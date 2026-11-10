# Adding an app to the HOP family

Every place that has to change when a new app (call it `NewHop`, slug `newhop`, host
`new.hel.ink`) joins the family. Written after FileHop joined (November 2026), and checked again when
PayHop joined (hop-assets v9), when several of these were missed at first: the HOP ID diagram on the home page, the terms and privacy pages,
the app's PWA icons and the GitHub numbers. Work top to bottom; each repository gets its own
pull request against `main`. A new app needs a new hop-assets version (copy the last one, add the app's mark, icons,
block map, floating tile and theme, name font and home art, and a home demo if it has one), because a deployed
version never changes.

## 0. Decide first

- [ ] Name, slug, host, tagline (one line, English; translated in step 3), colour.
- [ ] The mark: a pixel mark on an 8x8 grid like NoteHop, PixelHop and FileHop (32x32
      viewBox, 4 units per cell, `shape-rendering="crispEdges"`, a three-stop diagonal
      gradient, white cells for the cut-out). The same grid is the app's 3D preset and its
      floating tile, so the mark, the tile and the home page art always match.
- [ ] What the app stores about a person (for privacy and terms), and whether it hosts content
      (for the content and 18+ rules in the terms).

## 1. Shared assets: a new hop-assets version (HEL.ink)

A deployed version folder never changes (browsers and Cloudflare keep it a year).

- [ ] `cp -a public/hop-assets/vN public/hop-assets/vN+1`; edit only the copy.
- [ ] `apps/newhop/`: `mark.svg`, `icon-192.png`, `icon-512.png`, `apple-touch-icon.png`
      (mark centred on `#08090c`, about 54 % of the side, so the icon is also maskable).
- [ ] `hop-hero3d.js`: `MAPS.newhop` (the 8x8 grid) and `STOPS.newhop` (gradient).
- [ ] `hop-float.js`: the tile glyph (same grid as a path) and its place in the tile list;
      `hop-float.css`: its theme.
- [ ] `hop-home.js`: the app's home page demo, if it has one.
- [ ] `home/newhop-light.webp` and `home/newhop-dark.webp` (460x460): render them from the new
      `hop-hero3d.js` (mount the preset on a 460x460 canvas, wait for `settled()`, save
      `toDataURL('image/webp', 0.86)`); under 60 KiB each.
- [ ] `resources/hop-assets/apps.source.json`: `version`/`base` to vN+1, the app entry with all
      its icons, then `python3 scripts/hop_assets.py build` (writes `public/hop-assets/apps.json`,
      `docs/hop-family.md` and `packages/ui/resources/hop-assets/apps.json`) and `... check`.
- [ ] Every `hop-assets/vN/` path in the hub: `resources/views/home.blade.php`,
      `components/home-art.blade.php`, `partials/home-account.blade.php`,
      `resources/css/home.css`, `tests/**` (`git grep "hop-assets/vN"`).
- [ ] `docs/hop-assets.md`: a line under Versions saying what changed.

## 2. The hub (HEL.ink)

- [ ] `config/hop.php`: `apps.newhop` (url env, icon, colour, enabled flag),
      `registry_taglines`, `registry_repos`, the sign-out path, `event_apps`, the admin
      summary fields, `github.repos` (`navi-crwn/NewHop`), and the reserved names lists.
- [ ] `.env.example` (`NEWHOP_URL`, `NEWHOP_ENABLED`, `HOP_SSO_SECRET_NEWHOP` if it signs in
      with its own secret), `phpunit.xml`.
- [ ] `database/seeders/HopAppsSeeder.php` and `database/factories/HopAppFactory.php`:
      the registry row (`capabilities`: `events`, `admin_api`, …).
- [ ] `app/Support/FamilyStory.php`: `APPS` and the app's story, features, steps, FAQs and
      call to action (this feeds the home page section and `/apps/newhop`).
- [ ] `app/Support/EventRegistry.php`, if the app receives account events.
- [ ] `public/images/apps/newhop.webp` (the `/apps/newhop` preview image).
- [ ] Home page: `partials/home-mini-demo.blade.php`; the HOP ID diagram
      (`partials/home-account.blade.php`) places every app on a ring by itself, check it at
      phone width; the numbers strip ("N apps") and the marquee.
- [ ] Marketing pages, each with the new app where the others are named:
      `about`, `faq` (its own section and the jump link at the top, plus the answers that
      list every app, such as export and deletion), `help` (a user guide card),
      `how-it-works`, `security`, `open-source`, `products`, `contact`,
      `components/marketing-layout.blade.php` (footer "HOP family").
- [ ] **Privacy** (`marketing/privacy.blade.php`): a card for the app (what it stores, what it
      never sees), a line under retention, and the list of what HOP checks if the app hosts
      content.
- [ ] **Terms** (`marketing/terms.blade.php`): the sentence that lists the member apps, a card
      for the app, and the content-classification list if it hosts content. The dated legal
      text in `resources/legal/` changes only as a new dated version (owner decision: it can
      ask everyone to accept again).
- [ ] Status and watchdog: the app's health check, the uptime strip, `status-ticker`.
- [ ] `install.sh`, `install-family.sh`, READMEs in every language (the family table comes
      from `docs/hop-family.md`).
- [ ] After deploy: `php artisan db:seed --class='Database\Seeders\HopAppsSeeder'` and
      `php artisan hop:github-stats` once (otherwise "Numbers for this repository are not loaded
      yet" shows until the next hourly run).

## 3. Translations

- [ ] Every new string in all nine languages: en, zh-CN, de, id, ja, pt-BR, es, fr, ru
      (never Arabic). In the hub, `lang/*.json`; `tests/Feature/HubPathsAndErrorsTest.php`
      fails when a string in a view has no translation (the baseline file
      `tests/Fixtures/i18n-missing-baseline.txt` may only shrink).
- [ ] When a sentence that lists the apps changes, the key changes too: move every language's
      translation to the new key and add the new app's name to it.

## 4. Shared packages

- [ ] **hop-ui**: copy the hub's `public/hop-assets/apps.json` to
      `resources/hop-assets/apps.json`; add the icon name to `src/Apps.php` if it is new;
      tests that count apps; README and CHANGELOG. The sidebar "HOP family" list, the app
      switcher and the footer family list all read this manifest.
- [ ] **hop-auth**: the new host in `trusted_hosts` (chained sign-out), a new tag.

## 5. The other apps

- [ ] **LinkHop, BioHop** (Laravel): they read the manifest from hel.ink at run time, so marks
      update by themselves after the hub deploys. Still: bump `hop/ui` and `hop/auth`, the
      texts that name every app (`lang/*.json`, FAQ, features), the reserved names list,
      `scripts/sync-hop-assets.sh` only if their own home page uses something that changed.
- [ ] **NoteHop, PixelHop** (Go): the manifest is embedded:
      `scripts/sync-hop-assets.sh /path/to/HEL.ink` (set the version in the script first),
      the family tests (`internal/app/hopfamily_test.go`), texts that name every app
      (PixelHop `web/i18n/*.json`), READMEs, docs pages.
- [ ] **Imperium** (Go): `ver=` in `scripts/sync-hop-assets.sh`, add the mark to its file list,
      run it; the slug in `internal/app/app.go`, `handlers.go` (summaries), `nav.go` (KPI
      fields), `templates/user.html` (a card), `scripts/fakehop`, backup and restore scripts,
      `protocol/PROTOCOL.md`.

## 6. The new app itself

- [ ] Mark, favicon, `icon-192.png`, `icon-512.png`, `apple-touch-icon.png` copied byte for
      byte from the hop-assets version; `manifest.webmanifest` lists the SVG and both PNGs;
      `<link rel="icon">` and `<link rel="apple-touch-icon">` in the page head.
- [ ] Header: mark + name; on phones one row (primary action, language, theme, account).
- [ ] Footer "HOP family": HOP ID and every other public app, itself left out.
- [ ] Nine languages, reduced motion complete and static, no CDN scripts, family fonts
      (Inter, Instrument Serif italic accent, JetBrains Mono).
- [ ] `/hop/manifest` with its capabilities, `/healthz`, SSO through the hub.

## 7. Check before asking for review

- [ ] `curl -sI` every new URL (mark, icons, manifest, `/apps/newhop`) on the preview.
- [ ] Screenshots, light and dark, desktop and 390 px: hub home (tile, section, HOP ID diagram,
      open-source tabs, footer), `/apps/newhop`, FAQ, help, privacy, terms, a dashboard sidebar
      in one Laravel app and one Go app, Imperium's app list, the new app's own home page.
- [ ] Owner approves the preview before any pull request is opened.
